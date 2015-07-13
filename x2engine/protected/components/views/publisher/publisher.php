<?php
/*****************************************************************************************
 * X2Engine Open Source Edition is a customer relationship management program developed by
 * X2Engine, Inc. Copyright (C) 2011-2015 X2Engine Inc.
 * 
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY X2ENGINE, X2ENGINE DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more
 * details.
 * 
 * You should have received a copy of the GNU Affero General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 * 
 * You can contact X2Engine, Inc. P.O. Box 66752, Scotts Valley,
 * California 95067, USA. or at email address contact@x2engine.com.
 * 
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 * 
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * X2Engine" logo. If the display of the logo is not reasonably feasible for
 * technical reasons, the Appropriate Legal Notices must display the words
 * "Powered by X2Engine".
 *****************************************************************************************/

$form = $this->beginWidget('X2ActiveForm', array('id' => 'publisher-form')); 

$publisherCreated = true;

$that = $this; 
$echoTabRow = function ($tabs, $rowNum=1) use ($that) {
    ?><ul id='<?php echo $that->resolveId ('publisher-tabs-row-'.$rowNum); ?>' 
       style='display: none;'>
            <?php 
            // Publisher tabs
            foreach ($tabs as $tab) {
                ?> <li> <?php
                $tab->renderTitle ();
                ?> </li> <?php
            }
            ?>
        </ul><?php    
};

?>

<div id="<?php echo $this->resolveId ('publisher'); ?>" 
 <?php echo (sizeof ($tabs) > 4 ? 'class="multi-row-tabs-publisher"' : ''); ?>>
    <?php
    $tabsTmp = $tabs;
    if (sizeof ($tabs) > 4) {
        $rowNum = 0;
        while (sizeof ($tabsTmp)) {
            $tabRow = array_slice ($tabsTmp, 0, 3);
            $echoTabRow ($tabRow, ++$rowNum);
            $tabsTmp = array_slice ($tabsTmp, 3);
        }
    } else {
        $echoTabRow ($tabsTmp);
    }
    ?>
    <div class='clearfix sortable-widget-handle'></div>
    <div class="form x2-layout-island">
    <?php
    // Publisher tab content 
    foreach ($tabs as $tab) {
        $tab->renderTab (array (
            'form' => $form,
            'model' => $model,
            'associationType' => $associationType,
        ));
    }
    if(Yii::app()->user->isGuest){ 
    ?>
        <div class="row">
            <?php
            $this->widget('CCaptcha', array(
                'captchaAction' => '/actions/actions/captcha',
                'buttonOptions' => array(
                    'style' => 'display:block;',
                ),
            ));
            ?>
            <?php echo $form->textField($model, 'verifyCode'); ?>
        </div>
    <?php 
    } 
    echo CHtml::hiddenField('SelectedTab', ''); // currently selected tab  
    if ($associationType !== 'calendar') {
        echo $form->hiddenField($model, 'associationType'); 
        echo $form->hiddenField($model, 'associationId'); 
    }
    ?>
    <div class='row'>
        <input type='submit' value='Save' id='<?php echo $this->resolveId ('save-publisher'); ?>' 
         class='x2-button'>
    </div>
    </div>
</div>

<?php $this->endWidget(); ?>