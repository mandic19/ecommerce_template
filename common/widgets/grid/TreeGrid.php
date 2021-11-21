<?php

namespace common\widgets\grid;

use Closure;
use Yii;
use yii\base\InvalidConfigException;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\i18n\Formatter;

/**
 * TreeGrid renders a jQuery TreeGrid component.
 * The code was based in: https://github.com/yiisoft/yii2/blob/master/framework/grid/GridView.php
 *
 * @see https://github.com/maxazan/jquery-treegrid
 * @author Leandro Gehlen <leandrogehlen@gmail.com>
 */
class TreeGrid extends GridView
{
    /**
     * @var \yii\data\DataProviderInterface|\yii\data\BaseDataProvider
     */
    protected $clonedQuery;

    /**
     * @var string the default data column class if the class name is not explicitly specified when configuring a data column.
     * Defaults to 'leandrogehlen\treegrid\TreeColumn'.
     */
    public $dataColumnClass;

    /**
     * @var array the HTML attributes for the container tag of the grid view.
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */

    /**
     * @var array The plugin options
     */
    public $pluginOptions = [];

    /**
     * @var string name of key column used to build tree
     */
    public $keyColumnName;

    /**
     * @var string name of parent column used to build tree
     */
    public $parentColumnName;

    /**
     * @var mixed parent column value of root elements from data
     */
    public $parentRootValue = null;

    /**
     * @var array the HTML attributes for the container tag of the tree grid view.
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $tableOptions = ['class' => 'table table-striped table-primary table-body-relative tree-grid'];

    public $collapsable = false;

    public $collapseToggle;

    public $parentColumnWithAlias;

    private $children;


    public function init()
    {
        if ($this->dataProvider === null) {
            throw new InvalidConfigException('The "dataProvider" property must be set.');
        }
        if ($this->canBePaginated()) {
            $this->clonedQuery = clone $this->dataProvider->query;
            $column = $this->parentColumnWithAlias ?: $this->parentColumnName;
            $this->dataProvider->query->andWhere(['IS', $column, null]);
            $this->children = $this->findChildRecords($this->dataProvider->getModels());
        }
        if ($this->emptyText === null) {
            $this->emptyText = Yii::t('yii', 'No results found.');
        }
        if (!isset($this->options['id'])) {
            $this->options['id'] = $this->getId();
        }

        if ($this->formatter == null) {
            $this->formatter = Yii::$app->getFormatter();
        } elseif (is_array($this->formatter)) {
            $this->formatter = Yii::createObject($this->formatter);
        }
        if (!$this->formatter instanceof Formatter) {
            throw new InvalidConfigException('The "formatter" property must be either a Format object or a configuration array.');
        }

        if (!$this->keyColumnName) {
            throw new InvalidConfigException('The "keyColumnName" property must be specified"');
        }
        if (!$this->parentColumnName) {
            throw new InvalidConfigException('The "parentColumnName" property must be specified"');
        }
        $this->collapseToggle = $this->collapsable ? $this->renderCollapseToggle() : '';

        parent::init(); // TODO: Change the autogenerated stub
    }

    public function run()
    {
        $id = $this->options['id'];
        $options = Json::htmlEncode($this->pluginOptions);

        $view = $this->getView();
        TreeGridAsset::register($view);

        $view->registerJs("
            $('#$id table.tree-grid').treegrid($options);
            $('#$id .collapse-all').on('click', function(){
                $('#$id table.tree-grid').treegrid('collapseAll');
                $(this).addClass('active');
                $(this).siblings().removeClass('active');
            });
            $('#$id .expand-all').on('click', function(){
                $('#$id table.tree-grid').treegrid('expandAll');
                $(this).addClass('active');
                $(this).siblings().removeClass('active');
            });
        ");

        parent::run();
    }

    public function renderItems()
    {
        $body = $this->renderTableBody();
        $header = $this->showHeader ? $this->renderTableHeader() : false;
        $footer = $this->showFooter ? $this->renderTableFooter() : false;
        $table = array_filter([
            $header,
            $body,
            $footer
        ]);

        return Html::tag('table', implode("\n", $table), $this->tableOptions);
    }

    public function renderCollapseToggle()
    {
        $collapsed = isset($this->pluginOptions['initialState']) && $this->pluginOptions['initialState'] == 'collapsed';
        $collapseAll = Html::tag('span', Yii::t("app", "Collapse All"), [
            'class' => 'collapse-all ' . ($collapsed ? ' active' : '')
        ]);
        $expandAll = Html::tag('span', Yii::t("app", "Expand All"), [
            'class' => 'expand-all ' . (!$collapsed ? ' active' : '')
        ]);
        return Html::tag('div', "{$expandAll} / {$collapseAll}", [
            'class' => 'treegrid-collapse-toggle mr-4 py-2'
        ]);
    }

    /**
     * Renders the data models for the grid view.
     */
    public function renderTableBody()
    {
        $rows = [];
        $this->dataProvider->setKeys([]);
        $models = array_values($this->dataProvider->getModels());

        foreach ($this->children as $child) {
            $models[] = $child;
        }

        $models = $this->normalizeData($models, $this->parentRootValue);
        $this->dataProvider->setModels($models);
        $this->dataProvider->setKeys(null);
        $this->dataProvider->prepare();
        $keys = $this->dataProvider->getKeys();

        foreach ($models as $index => $model) {
            $key = $keys[$index];
            if ($this->beforeRow !== null) {
                $row = call_user_func($this->beforeRow, $model, $key, $index, $this);
                if (!empty($row)) {
                    $rows[] = $row;
                }
            }

            $rows[] = $this->renderTableRow($model, $key, $index);

            if ($this->afterRow !== null) {
                $row = call_user_func($this->afterRow, $model, $key, $index, $this);
                if (!empty($row)) {
                    $rows[] = $row;
                }
            }
        }

        if (empty($rows)) {
            $colspan = count($this->columns);
            return "<tr><td colspan=\"$colspan\">" . $this->renderEmpty() . "</td></tr>";
        } else {
            return implode("\n", $rows);
        }
    }

    /**
     * Normalize tree data
     * @param array $data
     * @param string $parentId
     * @return array
     * @throws \Exception
     */
    protected function normalizeData(array $data, $parentId = null)
    {
        $result = [];
        foreach ($data as $element) {
            if (ArrayHelper::getValue($element, $this->parentColumnName) == $parentId) {
                $result[] = $element;
                $children = $this->normalizeData($data, ArrayHelper::getValue($element, $this->keyColumnName));
                if ($children) {
                    $result = array_merge($result, $children);
                }
            }
        }
        return $result;
    }

    /**
     * Renders a table row with the given data model and key.
     * @param mixed $model the data model to be rendered
     * @param mixed $key the key associated with the data model
     * @param integer $index the zero-based index of the data model among the model array returned by [[dataProvider]].
     * @return string the rendering result
     */
    public function renderTableRow($model, $key, $index)
    {
        $cells = [];

        foreach ($this->columns as $column) {
            $cells[] = $column->renderDataCell($model, $key, $index);
        }
        if ($this->rowOptions instanceof Closure) {
            $options = call_user_func($this->rowOptions, $model, $key, $index, $this);
        } else {
            $options = $this->rowOptions;
        }
        $options['data-key'] = is_array($key) ? json_encode($key) : (string)$key;

        $id = ArrayHelper::getValue($model, $this->keyColumnName);
        Html::addCssClass($options, "treegrid-$id");

        $parentId = ArrayHelper::getValue($model, $this->parentColumnName);
        if ($parentId) {
            if (ArrayHelper::getValue($this->pluginOptions, 'initialState') == 'collapsed') {
                Html::addCssStyle($options, 'display: none;');
            }
            Html::addCssClass($options, "treegrid-parent-$parentId");
        }

        return Html::tag('tr', implode('', $cells), $options);
    }

    protected function findChildRecords(array $models, $results = [])
    {
        $parenIds = ArrayHelper::map($models, $this->keyColumnName, $this->keyColumnName);
        $query = clone $this->clonedQuery;
        $column = $this->parentColumnWithAlias ?: $this->parentColumnName;
        $children = $query->andWhere(['AND', ['IS NOT', $column, null], ['IN', $column, $parenIds]])->all();

        if (!empty($children)) {
            foreach ($children as $child) {
                $results[] = $child;
            }
            $results = $this->findChildRecords($children, $results);
        }
        return $results;
    }

    protected function canBePaginated()
    {
        if ($perPage = Yii::$app->request->get('per-page', 40) == -1) {
            return false;
        }

        if ($this->dataProvider->getPagination() && $this->dataProvider instanceof ActiveDataProvider) {
            return true;
        }
        return false;
    }

    public function renderSummary()
    {
        $count = $this->dataProvider->getCount();
        if ($count <= 0) {
            return '';
        }
        $summaryOptions = $this->summaryOptions;
        $tag = ArrayHelper::remove($summaryOptions, 'tag', 'div');
        if (($pagination = $this->dataProvider->getPagination()) !== false) {
            $totalCount = $this->dataProvider->getTotalCount() + count($this->children);
            $begin = $pagination->getPage() * $pagination->pageSize + 1;
            $end = $begin + $count - 1;
            if ($begin > $end) {
                $begin = $end;
            }
            $page = $pagination->getPage() + 1;
            $pageCount = $pagination->pageCount;
            if (($summaryContent = $this->summary) === null) {
                return Html::tag($tag, Yii::t('yii', 'Showing <b>{begin, number}-{end, number}</b> of <b>{totalCount, number}</b> {totalCount, plural, one{item} other{items}}.', [
                    'begin' => $begin,
                    'end' => $end,
                    'count' => $count,
                    'totalCount' => $totalCount,
                    'page' => $page,
                    'pageCount' => $pageCount,
                ]), $summaryOptions);
            }
        } else {
            $begin = $page = $pageCount = 1;
            $end = $totalCount = $count;
            if (($summaryContent = $this->summary) === null) {
                return Html::tag($tag, Yii::t('yii', 'Total <b>{count, number}</b> {count, plural, one{item} other{items}}.', [
                    'begin' => $begin,
                    'end' => $end,
                    'count' => $count,
                    'totalCount' => $totalCount,
                    'page' => $page,
                    'pageCount' => $pageCount,
                ]), $summaryOptions);
            }
        }

        if ($summaryContent === '') {
            return '';
        }

        return Html::tag($tag, Yii::$app->getI18n()->format($summaryContent, [
            'begin' => $begin,
            'end' => $end,
            'count' => $count,
            'totalCount' => $totalCount,
            'page' => $page,
            'pageCount' => $pageCount,
        ], Yii::$app->language), $summaryOptions);
    }
}
