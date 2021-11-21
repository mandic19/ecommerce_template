<?php

/* @var $this View */
/* @var $form ActiveForm */

/* @var $model LoginForm */

use common\models\LoginForm;
use yii\helpers\Html;
use yii\web\View;
use yii\widgets\ActiveForm;

$this->title = Yii::t('app', 'Login');

?>

<div class="login">
    <div class="login-wrapper">
        <?php $form = ActiveForm::begin(['id' => 'login-form']); ?>
        <h2 class="custom-title mb-4"><?= Html::encode($this->title) ?></h2>
        <?= $form->field($model, 'username')->textInput([
            'placeholder' => $model->getAttributeLabel('username')
        ])->label(false) ?>
        <?= $form->field($model, 'password')->passwordInput([
            'placeholder' => $model->getAttributeLabel('password')
        ])->label(false) ?>
        <div class="text-center">
            <?= Html::submitButton('Log in', ['class' => 'btn btn-secondary mr-3']) ?>
            <a href="#">Lost your password?</a>
        </div>
        <hr>
        <h2><i class="fa fa-paw"></i> Gentelella Alela!</h2>
        <p>©2021 All Rights Reserved. Ecommerce Template!</p>
        <?php ActiveForm::end(); ?>
    </div>
</div>
