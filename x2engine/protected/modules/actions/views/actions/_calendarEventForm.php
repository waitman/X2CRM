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

$submitButton = isset ($submitButton) ? $submitButton : true;
$htmlOptions = !isset ($htmlOptions) ? array () : $htmlOptions;

$form = $this->beginWidget ('CalendarEventActiveForm', array (
    'formModel' => $model,
    'htmlOptions' => $htmlOptions,
));
    echo $form->textArea ($model, 'actionDescription');


?>
    <div class='row'>
        <div class='cell'>
            <div class='cell'>
<?php
    echo $form->dateRangeInput ($model, 'dueDate', 'completeDate');
?>
            </div>
            <div class='cell'>
<?php

    echo '<div class="clearfix"></div>';

    echo $form->label ($model, 'allDay'); 
    echo $form->renderInput ($model, 'allDay');

    echo $form->label ($model, 'priority'); 
    echo $form->renderInput ($model, 'priority');

    echo $form->label ($model, 'color'); 
    echo $form->renderInput ($model, 'color');
?>
            </div>
        </div>
        <div class='cell'>
            <div class='cell'>
<?php

    echo $form->label ($model, 'assignedTo'); 
    echo $form->renderInput ($model, 'assignedTo');
?>
            </div>
            <div class='cell'>
<?php

    echo $form->label ($model, 'eventSubtype'); 
    echo $form->renderInput ($model, 'eventSubtype');

    echo $form->label ($model, 'eventStatus'); 
    echo $form->renderInput ($model, 'eventStatus');
?>
            </div>
            <div class='cell'>
<?php

    echo $form->label ($model, 'visibility'); 
    echo $form->renderInput ($model, 'visibility');

    echo $form->label ($model, 'associationType'); 
    echo $form->renderInput ($model, 'associationType');
    echo CHtml::hiddenField ('modelName', 'calendar'); 
?>
            </div>
        </div>
    </div>
<?php

    if ($submitButton) echo $form->submitButton ();

$this->endWidget ();

?>
