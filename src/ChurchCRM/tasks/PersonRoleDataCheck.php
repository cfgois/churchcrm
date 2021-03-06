<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\PersonQuery;

class PersonRoleDataCheck implements iTask
{

    private $count;

    public function __construct()
    {
        $personQuery = PersonQuery::create()->filterByFmrId(0)->find();
        $this->count = $personQuery->count();
    }

    public function isActive()
    {
        return $this->count > 0;
    }

    public function isAdmin()
    {
        return false;
    }

    public function getLink()
    {
        return SystemURLs::getRootPath() . '/SelectList.php?mode=person&FamilyRole=0&PersonColumn3=Family+Role';
    }

    public function getTitle()
    {
        return gettext('Falta informação sobre função familiar') . " (" . $this->count . ")";
    }

    public function getDesc()
    {
        return gettext("Falta informação sobre função familiar de algumas pessoas");
    }

}
