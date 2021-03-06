<?php
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\FamilyQuery;
use Propel\Runtime\ActiveQuery\Criteria;

$sMode = 'Active';
// Filter received user input as needed
if (isset($_GET['mode'])) {
    $sMode = FilterInput($_GET['mode']);
}
if (strtolower($sMode) == 'inactive') {
    $families = FamilyQuery::create()
        ->filterByDateDeactivated(null, Criteria::ISNOTNULL)
            ->orderByName()
            ->find();
} else {
    $sMode = 'Active';
    $families = FamilyQuery::create()
        ->filterByDateDeactivated(null)
            ->orderByName()
            ->find();
}

// Set the page title and include HTML header
$sPageTitle = gettext(ucfirst($sMode)) . ' ' . gettext('Family List');
require 'Include/Header.php'; ?>

<div class="pull-right">
  <a class="btn btn-success" role="button" href="FamilyEditor.php"> <span class="fa fa-plus"
                                                                          aria-hidden="true"></span><?= gettext('Adicionar Familia') ?>
  </a>
</div>
<p><br/><br/></p>
<div class="box">
    <div class="box-body">
        <table id="families" class="table table-striped table-bordered data-table" cellspacing="0" width="100%">
            <thead>
            <tr>
                <th><?= gettext('Familia') ?></th>
                <th><?= gettext('Endereço') ?></th>
                <th><?= gettext('Tel. Residencial') ?></th>
                <th><?= gettext('Celular') ?></th>
                <th><?= gettext('Email') ?></th>
            </tr>
            </thead>
            <tbody>

            <!--Populate the table with family details -->
            <?php foreach ($families as $family) {
    ?>
            <tr>
                <td><a href='FamilyView.php?FamilyID=<?= $family->getId() ?>'>
                        <span class="fa-stack">
                            <i class="fa fa-square fa-stack-2x"></i>
                            <i class="fa fa-search-plus fa-stack-1x fa-inverse"></i>
                        </span>
                    </a>
                    <a href='FamilyEditor.php?FamilyID=<?= $family->getId() ?>'>
                        <span class="fa-stack">
                            <i class="fa fa-square fa-stack-2x"></i>
                            <i class="fa fa-pencil fa-stack-1x fa-inverse"></i>
                        </span>
                    </a><?= $family->getName() ?></td>
                <td> <?= $family->getAddress() ?></td>
                <td><?= $family->getHomePhone() ?></td>
                <td><?= $family->getCellPhone() ?></td>
                <td><?= $family->getEmail() ?></td>
                 <?php

}
                ?>
            </tr>
            </tbody>
        </table>
    </div>
</div>

<script type="text/javascript">
  $(document).ready(function () {
    $('#families').dataTable({
      "language": {
        "url": window.CRM.root + "/skin/locale/datatables/" + window.CRM.locale + ".json"
      },
      responsive: true,
      "dom": 'T<"clear">lfrtip',
  
      "tableTools": {
        "sSwfPath": "//cdn.datatables.net/tabletools/2.2.3/swf/copy_csv_xls_pdf.swf"
      }
    });
  });
</script>

<?php
require 'Include/Footer.php';
?>
