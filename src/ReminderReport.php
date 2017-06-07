<?php
/*******************************************************************************
 *
 *  filename    : ReminderReport.php
 *  last change : 2003-09-03
 *  description : form to invoke user access report
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\dto\SystemConfig;

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!$_SESSION['bAdmin'] && SystemConfig::getValue('bCSVAdminOnly')) {
    Redirect('Menu.php');
    exit;
}

// Set the page title and include HTML header
$sPageTitle = gettext('Pledge Reminder Report');
require 'Include/Header.php';

// Is this the second pass?
if (isset($_POST['Submit'])) {
    $iFYID = FilterInput($_POST['FYID'], 'int');
    $_SESSION['idefaultFY'] = $iFYID;
    Redirect('Reports/ReminderReport.php?FYID='.$_SESSION['idefaultFY']);
} else {
    $iFYID = $_SESSION['idefaultFY'];
}

?>

<div class="box box-body">
    <form class="form-horizontal" method="post" action="Reports/ReminderReport.php">
        <div class="form-group">
            <label class="control-label col-sm-2" for="FYID"><?= gettext('Fiscal Year') ?>:</label>
            <div class="col-sm-2">
                <?php PrintFYIDSelect($iFYID, 'FYID') ?>
            </div>
        </div>

        <div class="form-group">
            <div class="col-sm-offset-2 col-sm-8">
                <button type="submit" class="btn btn-primary" name="Submit"><?= gettext('Create Report') ?></button>
                <button type="button" class="btn btn-default" name="Cancel"
                        onclick="javascript:document.location='Menu.php';"><?= gettext('Cancel') ?></button>
            </div>
        </div>

    </form>
</div>
<?php
require 'Include/Footer.php';
?>
