<?php

namespace common\widgets;

class Pjax extends \yii\widgets\Pjax
{
    public $timeout = 5000;
    public $gridId = null;

    public function run()
    {
        $view = $this->getView();
        $view->registerJs("
            var modalSubmittedHandle = function(e, xhr, btn, frm, data) {
                let pjaxId = data.pjax_id;
                let gridId = data.grid_id;
                
                if (xhr.success && (pjaxId == '$this->id' || gridId == '$this->gridId')) {
                    $.pjax.reload({
                        container:'#{$this->id}',
                        push: false, 
                        replace: false, 
                        timeout: 10000,
                    });
                }
                $(document).unbind('modal-submitted', modalSubmittedHandle);
            }
            $(document).bind('modal-submitted', modalSubmittedHandle);
        ");

        return parent::run(); // TODO: Change the autogenerated stub
    }
}