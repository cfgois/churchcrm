<!-- Adicionando JQuery -->
<script src="//code.jquery.com/jquery-3.2.1.min.js"></script>

<?php
/*******************************************************************************
 *
 *  filename    : PersonEditor.php
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001, 2002, 2003 Deane Barker, Chris Gebhardt
 *                Copyright 2004-2005 Michael Wilt
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/
 
 

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Note;

//Set the page title
$sPageTitle = gettext('Person Editor');

//Get the PersonID out of the querystring
if (array_key_exists('PersonID', $_GET)) {
    $iPersonID = FilterInput($_GET['PersonID'], 'int');
} else {
    $iPersonID = 0;
}

$sPreviousPage = '';
if (array_key_exists('previousPage', $_GET)) {
    $sPreviousPage = FilterInput($_GET['previousPage']);
}

// Security: User must have Add or Edit Records permission to use this form in those manners
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
if ($iPersonID > 0) {
    $sSQL = 'SELECT per_fam_ID FROM person_per WHERE per_ID = '.$iPersonID;
    $rsPerson = RunQuery($sSQL);
    extract(mysqli_fetch_array($rsPerson));

    if (mysqli_num_rows($rsPerson) == 0) {
        Redirect('Menu.php');
        exit;
    }

    if (!(
        $_SESSION['bEditRecords'] ||
        ($_SESSION['bEditSelf'] && $iPersonID == $_SESSION['iUserID']) ||
        ($_SESSION['bEditSelf'] && $per_fam_ID > 0 && $per_fam_ID == $_SESSION['iFamID'])
    )
    ) {
        Redirect('Menu.php');
        exit;
    }
} elseif (!$_SESSION['bAddRecords']) {
    Redirect('Menu.php');
    exit;
}
// Get Field Security List Matrix
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 5 ORDER BY lst_OptionSequence';
$rsSecurityGrp = RunQuery($sSQL);

while ($aRow = mysqli_fetch_array($rsSecurityGrp)) {
    extract($aRow);
    $aSecurityType[$lst_OptionID] = $lst_OptionName;
}

// Get the list of custom person fields
$sSQL = 'SELECT person_custom_master.* FROM person_custom_master ORDER BY custom_Order';
$rsCustomFields = RunQuery($sSQL);
$numCustomFields = mysqli_num_rows($rsCustomFields);

// Get the Groups this Person is assigned to
$sSQL = 'SELECT grp_ID, grp_Name, grp_hasSpecialProps, role.lst_OptionName AS roleName
		FROM group_grp
		LEFT JOIN person2group2role_p2g2r ON p2g2r_grp_ID = grp_ID
		LEFT JOIN list_lst role ON lst_OptionID = p2g2r_rle_ID AND lst_ID = grp_RoleListID
		WHERE person2group2role_p2g2r.p2g2r_per_ID = '.$iPersonID.'
		ORDER BY grp_Name';
    $rsAssignedGroups = RunQuery($sSQL);
    $sAssignedGroups = ',';

// Get all the Groups
$sSQL = 'SELECT grp_ID, grp_Name FROM group_grp ORDER BY grp_Name';
    $rsGroups = RunQuery($sSQL);
    

//Initialize the error flag
$bErrorFlag = false;
$sFirstNameError = '';
$sMiddleNameError = '';
$sLastNameError = '';
$sEmailError = '';
$sWorkEmailError = '';
$sBirthDateError = '';
$sBirthYearError = '';
$sDiaconoDateError = '';
$sPresbiteroDateError = '';
$sMembershipDateError = '';
$aCustomErrors = [];
$izero = 0;

$fam_Country = '';

$bNoFormat_HomePhone = true;
$bNoFormat_WorkPhone = true;
$bNoFormat_CellPhone = false;
$bNoBirthYear = false;

//Is this the second pass?
if (isset($_POST['PersonSubmit']) || isset($_POST['PersonSubmitAndAdd'])) {
    //Get all the variables from the request object and assign them locally
    $sTitle = FilterInput($_POST['Title']);
    $sFirstName = FilterInput($_POST['FirstName']);
    $sMiddleName = FilterInput($_POST['MiddleName']);
    $sLastName = FilterInput($_POST['LastName']);
    $sSuffix = FilterInput($_POST['Suffix']);
    $iGender = FilterInput($_POST['Gender'], 'int');
    //$iDiacono = FilterInput($_POST['Diacono'], 'int');
    $iDiacono = isset($_POST['Diacono']) ? 1 : 0 ;                                          //checks if person is presbitero or not
    $iPresbitero = isset($_POST['Presbitero']) ? 1 : 0 ;                                             

    // Person address stuff is normally surpressed in favor of family address info
    $sAddress1 = '';
    $sAddress2 = '';
    $sBairro = '';
    $sState = '';
    $sCity = '';
    $sZip = '';
    $sCountry = '';
    if (array_key_exists('Address1', $_POST)) {
        $sAddress1 = FilterInput($_POST['Address1']);
    }
    if (array_key_exists('Numero', $_POST)) {
        $sNumero = FilterInput($_POST['Numero']);
    }
	 if (array_key_exists('Address2', $_POST)) {
        $sAddress2 = FilterInput($_POST['Address2']);
    }
    if (array_key_exists('Bairro', $_POST)) {
        $sBairro = FilterInput($_POST['Bairro']);
    }
    if (array_key_exists('City', $_POST)) {
        $sCity = FilterInput($_POST['City']);
    }
    if (array_key_exists('Zip', $_POST)) {
        $sZip = FilterInput($_POST['Zip']);
    }
    if (array_key_exists('State', $_POST)) {
        $sState = FilterInput($_POST['State']);
    }

    // bevand10 2012-04-26 Add support for uppercase ZIP - controlled by administrator via cfg param
    if (SystemConfig::getValue('cfgForceUppercaseZip')) {
        $sZip = strtoupper($sZip);
    }

    if (array_key_exists('Country', $_POST)) {
        $sCountry = FilterInput($_POST['Country']);
    }

    $iFamily = FilterInput($_POST['Family'], 'int');
    $iFamilyRole = FilterInput($_POST['FamilyRole'], 'int');

    // Get their family's country in case person's country was not entered
    if ($iFamily > 0) {
        $sSQL = 'SELECT fam_Country FROM family_fam WHERE fam_ID = '.$iFamily;
        $rsFamCountry = RunQuery($sSQL);
        extract(mysqli_fetch_array($rsFamCountry));
    }

    $sCountryTest = SelectWhichInfo($sCountry, $fam_Country, false);
    
	 
    $sHomePhone = FilterInput($_POST['HomePhone']);
    $sWorkPhone = FilterInput($_POST['WorkPhone']);
    $sCellPhone = FilterInput($_POST['CellPhone']);
    $sEmail = FilterInput($_POST['Email']);
    $sWorkEmail = FilterInput($_POST['WorkEmail']);
    $iBirthMonth = FilterInput($_POST['BirthMonth'], 'int');
    $iBirthDay = FilterInput($_POST['BirthDay'], 'int');
    $iBirthYear = FilterInput($_POST['BirthYear'], 'int');
    $bHideAge = isset($_POST['HideAge']);
    $dDiaconoDate = FilterInput($_POST['DiaconoDate']);
    $dPresbiteroDate = FilterInput($_POST['PresbiteroDate']);
    $dMembershipDate = FilterInput($_POST['MembershipDate']);
    $iClassification = FilterInput($_POST['Classification'], 'int');
    $iEnvelope = 0;
    if (array_key_exists('EnvID', $_POST)) {
        $iEnvelope = FilterInput($_POST['EnvID'], 'int');
    }
    if (array_key_exists('updateBirthYear', $_POST)) {
        $iupdateBirthYear = FilterInput($_POST['updateBirthYear'], 'int');
    }

    $bNoFormat_HomePhone = isset($_POST['NoFormat_HomePhone']);
    $bNoFormat_WorkPhone = isset($_POST['NoFormat_WorkPhone']);
    $bNoFormat_CellPhone = isset($_POST['NoFormat_CellPhone']);
    $bNoBirthYear = isset($_POST['NoBirthYear']);
		
	
		
    //Adjust variables as needed
    if ($iFamily == 0) {
        $iFamilyRole = 0;
    }

    //Validate the Last Name.  If family selected, but no last name, inherit from family.
    if (strlen($sLastName) < 1 && !SystemConfig::getValue('bAllowEmptyLastName')) {
        if ($iFamily < 1) {
            $sLastNameError = gettext('You must enter a Last Name if no Family is selected.');
            $bErrorFlag = true;
        } else {
            $sSQL = 'SELECT fam_Name FROM family_fam WHERE fam_ID = '.$iFamily;
            $rsFamName = RunQuery($sSQL);
            $aTemp = mysqli_fetch_array($rsFamName);
            $sLastName = $aTemp[0];
        }
    }

    // If they entered a full date, see if it's valid
    if (strlen($iBirthYear) > 0) {
       if ($iBirthYear > 2155 || $iBirthYear < 1901) {
            $sBirthYearError = gettext('Ano de Nascimento Inválido. Permitido valores entre 1901 e 2155');
            $bErrorFlag = true;
        } elseif ($iBirthMonth > 0 && $iBirthDay > 0) {
            if (!checkdate($iBirthMonth, $iBirthDay, $iBirthYear)) {
                $sBirthDateError = gettext('Data de Nascimento Inválida.');
                $bErrorFlag = true;
            }
        }
    }
    
    // check if Membro_Nao_Comungante is minor
    
			$date = new DateTime($iBirthYear.'-'.$iBirthMonth.'-'.$iBirthDay);
			$threshold = new DateTime ('-18 years');
			if ($date <= $threshold && $iClassification == 2) {
				$sTopError = gettext('Pessoa com mais de 18 anos não pode ser Membro Não-Comungante');
            $bErrorFlag = true;	
			}

    
    
    // If they DONT ENTER A DATE, CHANGE IT TO ZERO
    if (strlen($iBirthYear) == 0 && !($bNoBirthYear)) {
			$sTopError = gettext('Insira um ano de Nascimento ou Selecione "Nao Informado"');
         $sBirthYearError = gettext('Insira um ano de Nascimento ou Selecione "Nao Informado"');
         $bErrorFlag = true;
    }
    
		//Check if gender is NULL
	   if ($iGender == '') {
            $sTopError = gettext('Não selecionou o Sexo');
            $bErrorFlag = true;
        }
        
   	//Check if bairro is NULL
	   //if ($sBairro == '') {
      //      $sTopError = gettext('Não selecionou o Bairro');
      //      $bErrorFlag = true;
      //  }
        
   //Check if diacono is female
	   if ($iDiacono == '1' && $iGender == '2') {
            $sTopError = gettext('Uma Mulher não pode ser Diácono');
            $bErrorFlag = true;
        }
  
  //Check if presbitero is female
	   if ($iPresbitero == '1' && $iGender == '2') {
            $sTopError = gettext('Uma Mulher não pode ser Presbítero');
            $bErrorFlag = true;
        }
        

    // Validate Friend Date if one was entered
    if (strlen($dDiaconoDate) > 0) {
        $dateString = parseAndValidateDate($dDiaconoDate, $locale = 'US', $pasfut = 'past');
        if ($dateString === false) {
            $sDiaconoDateError = '<span style="color: red; ">'
                .gettext('Data Inválida para Diácono').'</span>';
            $bErrorFlag = true;
        } else {
            $dDiaconoDate = $dateString;
        }
    }
    
     // Validate Friend Date if one was entered
    if (strlen($dPresbiteroDate) > 0) {
        $dateString = parseAndValidateDate($dPresbiteroDate, $locale = 'US', $pasfut = 'past');
        if ($dateString === false) {
            $sPresbiteroDateError = '<span style="color: red; ">'
                .gettext('Data Inválida para Presbítero').'</span>';
            $bErrorFlag = true;
        } else {
            $dPresbiteroDate = $dateString;
        }
    }

    // Validate Membership Date if one was entered
    if (strlen($dMembershipDate) > 0) {
        $dateString = parseAndValidateDate($dMembershipDate, $locale = 'US', $pasfut = 'past');
        if ($dateString === false) {
            $sMembershipDateError = '<span style="color: red; ">'
                .gettext('Not a valid Membership Date').'</span>';
            $bErrorFlag = true;
        } else {
            $dMembershipDate = $dateString;
        }
    }

    // Validate Email
    if (strlen($sEmail) > 0) {
        if (checkEmail($sEmail) == false) {
            $sEmailError = '<span style="color: red; ">'
                .gettext('Email is Not Valid').'</span>';
            $bErrorFlag = true;
        } else {
            $sEmail = $sEmail;
        }
    }

    // Validate Work Email
    if (strlen($sWorkEmail) > 0) {
        if (checkEmail($sWorkEmail) == false) {
            $sWorkEmailError = '<span style="color: red; ">'
                .gettext('Work Email is Not Valid').'</span>';
            $bErrorFlag = true;
        } else {
            $sWorkEmail = $sWorkEmail;
        }
    }

    // Validate all the custom fields
    $aCustomData = [];
    while ($rowCustomField = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH)) {
        extract($rowCustomField);

        if ($aSecurityType[$custom_FieldSec] == 'bAll' || $_SESSION[$aSecurityType[$custom_FieldSec]]) {
            $currentFieldData = FilterInput($_POST[$custom_Field]);

            $bErrorFlag |= !validateCustomField($type_ID, $currentFieldData, $custom_Field, $aCustomErrors);

            // assign processed value locally to $aPersonProps so we can use it to generate the form later
            $aCustomData[$custom_Field] = $currentFieldData;
        }
    }

    //If no errors, then let's update...
    if (!$bErrorFlag) {
        $sPhoneCountry = SelectWhichInfo($sCountry, $fam_Country, false);

        if (!$bNoFormat_HomePhone) {
            $sHomePhone = CollapsePhoneNumber($sHomePhone, $sPhoneCountry);
        }
        if (!$bNoFormat_WorkPhone) {
            $sWorkPhone = CollapsePhoneNumber($sWorkPhone, $sPhoneCountry);
        }
        if (!$bNoFormat_CellPhone) {
            $sCellPhone = CollapsePhoneNumber($sCellPhone, $sPhoneCountry);
        }
		  if ($bNoBirthYear) {
            $iBirthYear = '';
        }

        	$temp = $sAddress1.", ".$sNumero;
			$sAddress1=$temp;     
      

        // New Family (add)
        // Family will be named by the Last Name.
        if ($iFamily == -1) {
            $sSQL = "INSERT INTO family_fam (fam_Name, fam_Address1, fam_Address2, fam_Bairro, fam_City, fam_State, fam_Zip, fam_Country, fam_HomePhone, fam_WorkPhone, fam_CellPhone, fam_Email, fam_DateEntered, fam_EnteredBy)
					VALUES ('".$sLastName."','".$sAddress1."','".$sAddress2."','".$sBairro."','".$sCity."','".$sState."','".$sZip."','".$sCountry."','".$sHomePhone."','".$sWorkPhone."','".$sCellPhone."','".$sEmail."','".date('YmdHis')."',".$_SESSION['iUserID'].')';
            //Execute the SQL
            RunQuery($sSQL);
            //Get the key back
            $sSQL = 'SELECT MAX(fam_ID) AS iFamily FROM family_fam';
            $rsLastEntry = RunQuery($sSQL);
            extract(mysqli_fetch_array($rsLastEntry));
        }

        if ($bHideAge) {
            $per_Flags = 1;
        } else {
            $per_Flags = 0;
        }

			// junta o endereço com o numero, e guarda na variavel address1
			


        // New Person (add)
      if ($iPersonID < 1) {
            $iEnvelope = 0;
            $sSQL = "INSERT INTO person_per (per_Diacono, per_Presbitero, per_Title, per_FirstName, per_MiddleName, per_LastName, per_Suffix, per_Gender, per_Address1, per_Address2, per_Bairro, per_City, per_State, per_Zip, per_Country, per_HomePhone, per_WorkPhone, per_CellPhone, per_Email, per_WorkEmail, per_BirthDay, per_BirthMonth, per_Envelope, per_fam_ID, per_fmr_ID, per_MembershipDate, per_cls_ID, per_DateEntered, per_EnteredBy, per_DiaconoDate, per_BirthYear, per_PresbiteroDate, per_Flags )
			         VALUES ('".$iDiacono."','".$iPresbitero."','".$sTitle."','".$sFirstName."','".$sMiddleName."','".$sLastName."','".$sSuffix."',".$iGender.",'".$sAddress1."','".$sAddress2."','".$sBairro."','".$sCity."','".$sState."','".$sZip."','".$sCountry."','".$sHomePhone."','".$sWorkPhone."','".$sCellPhone."','".$sEmail."','".$sWorkEmail."',".$iBirthDay.','.$iBirthMonth.','.$iEnvelope.','.$iFamily.','.$iFamilyRole.',';
            if (strlen($dMembershipDate) > 0) {
                $sSQL .= '"'.$dMembershipDate.'"';
            } else {
                $sSQL .= 'NULL';
            }
            $sSQL .= ','.$iClassification.",'".date('YmdHis')."',".$_SESSION['iUserID'].',';
            if (strlen($dDiaconoDate) > 0) {
                $sSQL .= '"'.$dDiaconoDate.'"';
            } else {
                $sSQL .= 'NULL';
            }
            $sSQL .= ',';
            if (strlen($iBirthYear) > 0) {
                $sSQL .= '"'.$iBirthYear.'"';
            } else {
                $sSQL .= 'NULL';
            }
            $sSQL .= ',';
            if (strlen($dPresbiteroDate) > 0) {
                $sSQL .= '"'.$dPresbiteroDate.'"';
            } else {
                $sSQL .= 'NULL';
            }
            $sSQL .= ', '.$per_Flags;
            $sSQL .= ')';
            $bGetKeyBack = true;
            
				
            
            // Existing person (update)
        } else {
            $sSQL = "UPDATE person_per SET per_Title = '".$sTitle."',per_FirstName = '".$sFirstName."',per_MiddleName = '".$sMiddleName."', per_LastName = '".$sLastName."', per_Suffix = '".$sSuffix."', per_Gender = ".$iGender.", per_Diacono = ".$iDiacono.", per_Presbitero = '".$iPresbitero."', per_Address1 = '".$sAddress1."', per_Address2 = '".$sAddress2."', per_Bairro = '".$sBairro."', per_City = '".$sCity."', per_State = '".$sState."', per_Zip = '".$sZip."', per_Country = '".$sCountry."', per_HomePhone = '".$sHomePhone."', per_WorkPhone = '".$sWorkPhone."', per_CellPhone = '".$sCellPhone."', per_Email = '".$sEmail."', per_WorkEmail = '".$sWorkEmail."', per_BirthMonth = ".$iBirthMonth.', per_BirthDay = '.$iBirthDay.', per_fam_ID = '.$iFamily.', per_Fmr_ID = '.$iFamilyRole.', per_cls_ID = '.$iClassification.', per_MembershipDate = '; 

            if (strlen($dMembershipDate) > 0) {
                $sSQL .= '"'.$dMembershipDate.'"';
            } else {
                $sSQL .= 'NULL';
            }

            if ($_SESSION['bFinance']) {
                $sSQL .= ', per_Envelope = '.$iEnvelope;
            }

            $sSQL .= ", per_DateLastEdited = '".date('YmdHis')."', per_EditedBy = ".$_SESSION['iUserID'].', per_DiaconoDate =';

            if (strlen($dDiaconoDate) > 0) {
                $sSQL .= '"'.$dDiaconoDate.'"';
            } else {
                $sSQL .= 'NULL';
            }
            $sSQL .= ', per_PresbiteroDate = ';

            if (strlen($dPresbiteroDate) > 0) {
                $sSQL .= '"'.$dPresbiteroDate.'"';
            } else {
                $sSQL .= 'NULL';
            }
            $sSQL .= ', per_BirthYear =';
            if (strlen($iBirthYear) > 0) {
                $sSQL .= '"'.$iBirthYear.'"';
            } else {
                $sSQL .= 'NULL';
            }
                        
            $sSQL .= ', per_Flags='.$per_Flags;

            $sSQL .= ' WHERE per_ID = '.$iPersonID;

            $bGetKeyBack = false;
        }

       //Execute the SQL
		 //echo 'valor familia' ;      
       //echo $fam_Address1; 
       //echo $sSQL;
       RunQuery($sSQL); 

        $note = new Note();
        $note->setEntered($_SESSION['iUserID']);
        // If this is a new person, get the key back and insert a blank row into the person_custom table
        if ($bGetKeyBack) {
            $sSQL = 'SELECT MAX(per_ID) AS iPersonID FROM person_per';
            $rsPersonID = RunQuery($sSQL);
            extract(mysqli_fetch_array($rsPersonID));
            $sSQL = "INSERT INTO person_custom (per_ID) VALUES ('".$iPersonID."')";
            RunQuery($sSQL);
            $note->setPerId($iPersonID);
            $note->setText(gettext('Created'));
            $note->setType('create');
        } else {
            $note->setPerId($iPersonID);
            $note->setText(gettext('Updated'));
            $note->setType('edit');
        }
        $note->save();

        // Update the custom person fields.
        if ($numCustomFields > 0) {
            mysqli_data_seek($rsCustomFields, 0);
            $sSQL = '';
            while ($rowCustomField = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH)) {
                extract($rowCustomField);
                if ($aSecurityType[$custom_FieldSec] == 'bAll' || $_SESSION[$aSecurityType[$custom_FieldSec]]) {
                    $currentFieldData = trim($aCustomData[$custom_Field]);
                    sqlCustomField($sSQL, $type_ID, $currentFieldData, $custom_Field, $sPhoneCountry);
                }
            }

            // chop off the last 2 characters (comma and space) added in the last while loop iteration.
            if ($sSQL > '') {
                $sSQL = 'REPLACE INTO person_custom SET '.$sSQL.' per_ID = '.$iPersonID;
                //Execute the SQL
                RunQuery($sSQL);
            }
        }

        // Check for redirection to another page after saving information: (ie. PersonEditor.php?previousPage=prev.php?a=1;b=2;c=3)
        if ($sPreviousPage != '') {
            $sPreviousPage = str_replace(';', '&', $sPreviousPage);
            Redirect($sPreviousPage.$iPersonID);
        } elseif (isset($_POST['PersonSubmit'])) {
            //Send to the view of this person
            Redirect('PersonView.php?PersonID='.$iPersonID);
        } else {
            //Reload to editor to add another record
            Redirect('PersonEditor.php');
        }
    }

    // Set the envelope in case the form failed.
    $per_Envelope = $iEnvelope;
} else {

    //FirstPass
    //Are we editing or adding?
    if ($iPersonID > 0) {
        //Editing....
        //Get all the data on this record

        $sSQL = 'SELECT * FROM person_per LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_ID = '.$iPersonID;
        $rsPerson = RunQuery($sSQL);
        extract(mysqli_fetch_array($rsPerson));

        $sTitle = $per_Title;
        $sFirstName = $per_FirstName;
        $sMiddleName = $per_MiddleName;
        $sLastName = $per_LastName;
        $sSuffix = $per_Suffix;
        $iGender = $per_Gender;
        $iDiacono = $per_Diacono;
        $iPresbitero = $per_Presbitero;
        $sAddress1 = $per_Address1;
        $sNumero = ''; 

			if (  (strlen($per_Address1) < 2) && (strlen($fam_Address1)) ) {        
	        $sAddress1 = $fam_Address1;

 	   	 }
        $sAddress2 = $per_Address2;
         
         if ( (strlen($per_Address2) < 1) && (strlen($fam_Address2)) ) {        
	        $sAddress2 = $fam_Address2;
   	 }        
        
if ( (strlen($per_Address2) < 1) && (strlen($fam_Address2)) ) {        
	        $sAddress2 = $fam_Address2;
   	 }
			
			if ( (strlen($sBairro) < 1) && (strlen($fam_Bairro)) ) {        
	        $sBairro = $fam_Bairro;
   	 }        
        
        $sCity = $per_City;
        $sState = $per_State;
        $sZip = $per_Zip;
        
        if ( (strlen($sZip) < 1) && (strlen($fam_Zip)) ) {        
	        $sZip = $fam_Zip;
   	 }          
        
        $sCountry = $per_Country;
        $sHomePhone = $per_HomePhone;
        
        if ( (strlen($sHomePhone) < 1) && (strlen($fam_HomePhone)) ) {        
	        $sHomePhone = $fam_HomePhone;
   	 }
   	    
        $sWorkPhone = $per_WorkPhone;
        $sCellPhone = $per_CellPhone;
        $sEmail = $per_Email;
        $sWorkEmail = $per_WorkEmail;
        $iBirthMonth = $per_BirthMonth;
        $iBirthDay = $per_BirthDay;
        $iBirthYear = $per_BirthYear;
        $bHideAge = ($per_Flags & 1) != 0;
        $iOriginalFamily = $per_fam_ID;
        $iFamily = $per_fam_ID;
        $iFamilyRole = $per_fmr_ID;
        $dMembershipDate = $per_MembershipDate;
        $dDiaconoDate = $per_DiaconoDate;
        $dPresbiteroDate = $per_PresbiteroDate;
        $iClassification = $per_cls_ID;
        $iViewAgeFlag = $per_Flags;

        $sPhoneCountry = SelectWhichInfo($sCountry, $fam_Country, false);

        $sHomePhone = ExpandPhoneNumber($per_HomePhone, $sPhoneCountry, $bNoFormat_HomePhone);
        $sWorkPhone = ExpandPhoneNumber($per_WorkPhone, $sPhoneCountry, $bNoFormat_WorkPhone);
        $sCellPhone = ExpandPhoneNumber($per_CellPhone, $sPhoneCountry, $bNoFormat_CellPhone);

        //The following values are True booleans if the family record has a value for the
        //indicated field.  These are used to highlight field headers in red.
        $bFamilyAddress1 = strlen($fam_Address1);
        $bFamilyAddress2 = strlen($fam_Address2);
        $bFamilyBairro = strlen($fam_Bairro);
        $bFamilyCity = strlen($fam_City);
        $bFamilyState = strlen($fam_State);
        $bFamilyZip = strlen($fam_Zip);
        $bFamilyCountry = strlen($fam_Country);
        $bFamilyHomePhone = strlen($fam_HomePhone);
        $bFamilyWorkPhone = strlen($fam_WorkPhone);
        $bFamilyCellPhone = strlen($fam_CellPhone);
        $bFamilyEmail = strlen($fam_Email);

        $sSQL = 'SELECT * FROM person_custom WHERE per_ID = '.$iPersonID;
        $rsCustomData = RunQuery($sSQL);
        $aCustomData = [];
        if (mysqli_num_rows($rsCustomData) >= 1) {
            $aCustomData = mysqli_fetch_array($rsCustomData, MYSQLI_BOTH);
        }
    } else {
        //Adding....
        //Set defaults

           
        $sTitle = '';
        $sFirstName = '';
        $sMiddleName = '';
        $sLastName = '';
        $sSuffix = '';
        $iGender = '';
        $iDiacono = 0;
        $iPresbitero = 0;
        $sAddress1 = '';
        $sAddress2 = '';
        $sBairro = $per_Bairro;
        $sCity = SystemConfig::getValue('sDefaultCity');
        $sState = 'RJ';
        $sZip = '';
        $sCountry = SystemConfig::getValue('sDefaultCountry');
        $sHomePhone = '';
        $sWorkPhone = '';
        $sCellPhone = '';
        $sEmail = '';
        $sWorkEmail = '';
        $iBirthMonth = 0;
        $iBirthDay = 0;
        $iBirthYear = '';
        $bHideAge = 0;
        $iOriginalFamily = 0;
        $iFamily = '0';
        $iFamilyRole = '0';
        $dMembershipDate = '';
        $dDiaconoDate = '';
        $dPresbiteroDate = '';
        $iClassification = '0';
        $iViewAgeFlag = 0;
        $sPhoneCountry = '';

        $sHomePhone = '';
        $sWorkPhone = '';
        $sCellPhone = '';

        //The following values are True booleans if the family record has a value for the
        //indicated field.  These are used to highlight field headers in red.
        $bFamilyAddress1 = 0;
        $bFamilyAddress2 = 0;
        $bFamilyBairro = 0;
        $bFamilyCity = 0;
        $bFamilyState = 0;
        $bFamilyZip = 0;
        $bFamilyCountry = 0;
        $bFamilyHomePhone = 0;
        $bFamilyWorkPhone = 0;
        $bFamilyCellPhone = 0;
        $bFamilyEmail = 0;
        $bHomeBound = false;
        $aCustomData = [];
       
       if ($_GET['FamilyID']){
				$temp = $_GET['FamilyID'];
            $sSQL = 'SELECT per_Address1 FROM person_per WHERE per_fam_ID = '.$temp." LIMIT 1";            
            $rstemp = RunQuery($sSQL);
            $aTemp = mysqli_fetch_array($rstemp);
				if (strlen($aTemp[0]) > 0){            
            $sAddress1 = $aTemp[0];
         	}
         	$aTemp='';
            
            $sSQL = 'SELECT per_Address2 FROM person_per WHERE per_fam_ID = '.$temp." LIMIT 1";            
            $rstemp = RunQuery($sSQL);
            $aTemp = mysqli_fetch_array($rstemp);
            if (strlen($aTemp[0]) > 0){            
            $sAddress2 = $aTemp[0];
         	}
         	$aTemp='';
         	
            $sSQL = 'SELECT per_Zip FROM person_per WHERE per_fam_ID = '.$temp." LIMIT 1";            
            $rstemp = RunQuery($sSQL);
            $aTemp = mysqli_fetch_array($rstemp);
            if (strlen($aTemp[0]) > 0){            
            $sZip = $aTemp[0];
         	}
         	$aTemp='';
         	
				$sSQL = 'SELECT per_Bairro FROM person_per WHERE per_fam_ID = '.$temp." LIMIT 1";            
            $rstemp = RunQuery($sSQL);
            $aTemp = mysqli_fetch_array($rstemp);
 				if (strlen($aTemp[0]) > 0){            
            $sBairro = $aTemp[0];
         	}
         	$aTemp='';
         	           
            $sSQL = 'SELECT per_City FROM person_per WHERE per_fam_ID = '.$temp." LIMIT 1";            
            $rstemp = RunQuery($sSQL);
            $aTemp = mysqli_fetch_array($rstemp);
	          if (strlen($aTemp[0]) > 0){            
            $sCity = $aTemp[0];
         	}
         	$aTemp='';
         	 
            $sSQL = 'SELECT per_State FROM person_per WHERE per_fam_ID = '.$temp." LIMIT 1";            
            $rstemp = RunQuery($sSQL);
            $aTemp = mysqli_fetch_array($rstemp);
            if (strlen($aTemp[0]) > 0){            
            $sState = $aTemp[0];
         	}
         	$aTemp='';
         	
            $sSQL = 'SELECT per_Country FROM person_per WHERE per_fam_ID = '.$temp." LIMIT 1";            
            $rstemp = RunQuery($sSQL);
            $aTemp = mysqli_fetch_array($rstemp);
            if (strlen($aTemp[0]) > 0){            
            $sCountry = $aTemp[0];
         	}
         	$aTemp='';
         	     
			 			    
       } 
      
        
    }
}

//Get Classifications for the drop-down
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence';
$rsClassifications = RunQuery($sSQL);

//Get Families for the drop-down
$sSQL = 'SELECT * FROM family_fam ORDER BY fam_Name';
$rsFamilies = RunQuery($sSQL);

//Get Family Roles for the drop-down
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 2 ORDER BY lst_OptionSequence';
$rsFamilyRoles = RunQuery($sSQL);

require 'Include/Header.php';

?>
<form method="post" action="PersonEditor.php?PersonID=<?= $iPersonID ?>" name="PersonEditor">
    <!--<div class="alert alert-info alert-dismissable">
        <i class="fa fa-info"></i>
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <strong><span
                style="color: red;"><?= gettext('Red text') ?></span></strong> <?php echo gettext('indicates items inherited from the associated family record.'); ?>
    </div> -->
    <?php if ($bErrorFlag) {
    ?>
        <div class="alert alert-danger alert-dismissable">
            <i class="fa fa-ban"></i>
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <?= gettext('Invalid fields or selections. Changes not saved! Please correct and try again!') ?>
        
			<?php if ($sTopError) {
    ?><br><font
                            color="blue"><?php echo $sTopError ?></font><?php

				} ?>        
        
        </div>
        
				        
        
        
    <?php

} ?>
    <div class="box box-info clearfix">
        <div class="box-header">
            <h3 class="box-title"><?= gettext('Informações Pessoais') ?></h3>
            <!-- <div class="pull-right"><br/>
                <input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="PersonSubmit">
            </div> -->
        </div><!-- /.box-header -->
        <div class="box-body">
            <div class="form-group">
                <div class="row">
                    <div class="col-md-2">
                        <label><?= gettext('Gender') ?>:</label>
                        <select name="Gender" class="form-control">
                            <option value="0"><?= gettext('Select Gender') ?></option>
                            <option value="0" disabled>-----------------------</option>
                            <option value="1" <?php if ($iGender == 1) {
    echo 'selected';
} ?>><?= gettext('Male') ?></option>
                            <option value="2" <?php if ($iGender == 2) {
    echo 'selected';
} ?>><?= gettext('Female') ?></option>
                        </select>
                    </div>
                    <!--<div class="col-md-3">
                        <label for="Title"><?= gettext('Title') ?>:</label>
                        <input type="text" name="Title" id="Title"
                               value="<?= htmlentities(stripslashes($sTitle), ENT_NOQUOTES, 'UTF-8') ?>"
                               class="form-control" placeholder="<?= gettext('Mr., Mrs., Dr., Rev.') ?>">
                    </div>-->
                </div>
                
				 
                <p/>
                <div class="row">
                    <div class="col-md-2">
                        <label for="FirstName"><?= gettext('Primeiro Nome') ?>:</label>
                        <input type="text" name="FirstName" id="FirstName"
                               value="<?= htmlentities(stripslashes($sFirstName), ENT_NOQUOTES, 'UTF-8') ?>"
                               class="form-control">
                        <?php if ($sFirstNameError) {
    ?><br><font
                            color="red"><?php echo $sFirstNameError ?></font><?php

} ?>
                    </div>

                    <div class="col-md-4">
                        <label for="MiddleName"><?= gettext('Nomes do Meio') ?>:</label>
                        <input type="text" name="MiddleName" id="MiddleName"
                               value="<?= htmlentities(stripslashes($sMiddleName), ENT_NOQUOTES, 'UTF-8') ?>"
                               class="form-control">
                        <?php if ($sMiddleNameError) {
    ?><br><font
                            color="red"><?php echo $sMiddleNameError ?></font><?php

} ?>
                    </div>

                    <div class="col-md-3">
                        <label for="LastName"><?= gettext('Sobrenome') ?>:</label>
                        <input type="text" name="LastName" id="LastName"
                               value="<?= htmlentities(stripslashes($sLastName), ENT_NOQUOTES, 'UTF-8') ?>"
                               class="form-control">
                        <?php if ($sLastNameError) {
    ?><br><font
                            color="red"><?php echo $sLastNameError ?></font><?php

} ?>
                    </div>

                    <div class="col-md-1">
                        <label for="Suffix"><?= gettext('Suffix') ?>:</label>
                        <input type="text" name="Suffix" id="Suffix"
                               value="<?= htmlentities(stripslashes($sSuffix), ENT_NOQUOTES, 'UTF-8') ?>"
                               placeholder="<?= gettext('Jr., Sr., III') ?>" class="form-control">
                    </div>
                </div>
                <p/>
                <div class="row">
                    <div class="col-md-2">
                        <label><?= gettext('Mês de Nascimento') ?>:</label>
                        <select name="BirthMonth" class="form-control">
                            <option value="0" <?php if ($iBirthMonth == 0) {
    echo 'selected';
} ?>><?= gettext('Select Month') ?></option>
                            <option value="01" <?php if ($iBirthMonth == 1) {
    echo 'selected';
} ?>><?= gettext('January') ?></option>
                            <option value="02" <?php if ($iBirthMonth == 2) {
    echo 'selected';
} ?>><?= gettext('February') ?></option>
                            <option value="03" <?php if ($iBirthMonth == 3) {
    echo 'selected';
} ?>><?= gettext('March') ?></option>
                            <option value="04" <?php if ($iBirthMonth == 4) {
    echo 'selected';
} ?>><?= gettext('April') ?></option>
                            <option value="05" <?php if ($iBirthMonth == 5) {
    echo 'selected';
} ?>><?= gettext('May') ?></option>
                            <option value="06" <?php if ($iBirthMonth == 6) {
    echo 'selected';
} ?>><?= gettext('June') ?></option>
                            <option value="07" <?php if ($iBirthMonth == 7) {
    echo 'selected';
} ?>><?= gettext('July') ?></option>
                            <option value="08" <?php if ($iBirthMonth == 8) {
    echo 'selected';
} ?>><?= gettext('August') ?></option>
                            <option value="09" <?php if ($iBirthMonth == 9) {
    echo 'selected';
} ?>><?= gettext('September') ?></option>
                            <option value="10" <?php if ($iBirthMonth == 10) {
    echo 'selected';
} ?>><?= gettext('October') ?></option>
                            <option value="11" <?php if ($iBirthMonth == 11) {
    echo 'selected';
} ?>><?= gettext('November') ?></option>
                            <option value="12" <?php if ($iBirthMonth == 12) {
    echo 'selected';
} ?>><?= gettext('December') ?></option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label><?= gettext('Dia do Nascimento') ?>:</label>
                        <select name="BirthDay" class="form-control">
                            <option value="0"><?= gettext('Select Day') ?></option>
                            <?php for ($x = 1; $x < 32; $x++) {
    if ($x < 10) {
        $sDay = '0'.$x;
    } else {
        $sDay = $x;
    } ?>
                                <option value="<?= $sDay ?>" <?php if ($iBirthDay == $x) {
        echo 'selected';
    } ?>><?= $x ?></option>
                            <?php

} ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label><?= gettext('Ano de Nascimento') ?>:</label>
                        <input type="number" name="BirthYear" value="<?php echo $iBirthYear ?>" maxlength="4" size="5"
                               placeholder="YYYY" class="form-control">
                                                              
                        <?php if ($sBirthYearError) {
    ?><font color="red"><br><?php echo $sBirthYearError ?>
                            </font><?php

} ?>
                        <?php if ($sBirthDateError) {
    ?><font
                            color="red"><?php echo $sBirthDateError ?></font><?php

} ?>									
                    </div>
                    
<div class="col-md-3">
                       <br><br> <input type="checkbox" name="NoBirthYear"
                                   value="0" <?php if ($bNoBirthYear) {
                            echo ' checked';
                        } ?>><?= gettext('Ano Não Informado') ?>    							
                    </div>
                                        
                    </div>
                    
                                        
             </div>
                                       
                </div>
            </div>
                    
    
    <div class="box box-info clearfix">
        <div class="box-header">
            <h3 class="box-title"><?= gettext('Family Info') ?></h3>
            <!-- <div class="pull-right"><br/>
                <input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="PersonSubmit">
            </div> -->
        </div><!-- /.box-header -->
        <div class="box-body">
            <div class="form-group col-md-2">
                <label><?= gettext('Papel na Família') ?>:</label>
                <select name="FamilyRole" class="form-control">
                    <option value="0"><?= gettext('Unassigned') ?></option>
                    <option value="0" disabled>-----------------------</option>
                    <?php while ($aRow = mysqli_fetch_array($rsFamilyRoles)) {
    extract($aRow);
    echo '<option value="'.$lst_OptionID.'"';
    if ($iFamilyRole == $lst_OptionID) {
        echo ' selected';
    }
    echo '>'.$lst_OptionName.'&nbsp;';
} ?>
                </select>
            </div>
				
				<?php       	
								
								if ($_GET['FamilyID']){ 
								$temp = $_GET['FamilyID'];
            				$sSQL = 'SELECT fam_Name FROM family_fam WHERE fam_ID = '.$temp;            
          				   $stemp = RunQuery($sSQL);
          				   $nomefamilia = mysqli_fetch_array($stemp);
          				   echo '<span style="padding:20px; "><h4>Adicionando Membro à Familia <span style="color:red; "><b> '.$nomefamilia[0].' </b></span></h4></span>';  
          				              				
								?>
						            			
								<?php } 
								
								else{ ?>			
								
				
            <div class="form-group col-md-6">
                <label><?= gettext('Family'); ?>:</label>
                <select name="Family" class="form-control">
                    <option value="0" selected><?= gettext('Unassigned') ?></option>
                    <option value="-1"><?= gettext('Create a new family (using last name)') ?></option>
                    <option value="0" disabled>-----------------------</option>
                    <?php while ($aRow = mysqli_fetch_array($rsFamilies)) {
    extract($aRow);

    echo '<option value="'.$fam_ID.'"';
    if ($iFamily == $fam_ID || $_GET['FamilyID'] == $fam_ID) {
        echo ' selected';
    }
    echo '>'.$fam_Name.'&nbsp;'.FormatAddressLine($fam_Address1, $fam_City, $fam_State);
} ?>
                </select>
            </div> <?php } ?>	
        </div>
    </div>
    
    <div class="box box-info clearfix">
        <div class="box-header">
            <h3 class="box-title"><?= gettext('Contact Info') ?></h3>

            <!-- <div class="pull-right"><br/>
                <input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="PersonSubmit">
            </div> -->
        </div><!-- /.box-header -->
        <div class="box-body">
            <?php if (!SystemConfig::getValue('bHidePersonAddress')) { /* Person Address can be hidden - General Settings */ ?>
                <div class="row">
                    <div class="form-group">
                        <div class="col-md-1">


							<label for="Zip">
                            <?php if ($bFamilyZip) {
        //echo '<span style="color: red;">';
    }

    echo gettext('CEP').':';

    if ($bFamilyZip) {
        echo '</span>';
    } ?>
                        </label>
                        
                        
                        <input type="text" id="Zip" name="Zip" class="form-control"
                            <?php
                            // bevand10 2012-04-26 Add support for uppercase ZIP - controlled by administrator via cfg param
                            if (SystemConfig::getValue('cfgForceUppercaseZip')) {
                                echo 'style="text-transform:uppercase" ';
                            }

    echo 'value="'.htmlentities(stripslashes($sZip), ENT_NOQUOTES, 'UTF-8').'" '; ?>
                               maxlength="10" size="8">

							</div>
                           <div class="col-md-4">
                           
                           
                            <label>
                                <?php if ($bFamilyAddress1) {
    //echo '<span style="color: red;">';
}

    //echo gettext('Address').' 1: (Somente Address1 e Numero) ';
    echo 'Logradouro:';

    if ($bFamilyAddress1) {
        echo '</span>';
    } ?>
                            </label>
                            <input type="text" id="Address1" name="Address1"
                                   value="<?= htmlentities(stripslashes((explode(",", $sAddress1, 2)[0])), ENT_NOQUOTES, 'UTF-8') ?>"
                                   size="30" maxlength="50" class="form-control">
                        </div>

							<div class="col-md-1">
                           
                           
                            <label>Número</label>
                            <input type="text" id="Numero" name="Numero"
                                   value="<?= htmlentities(stripslashes(substr($sAddress1, strpos($sAddress1, ",") + 1)), ENT_NOQUOTES, 'UTF-8') ?>"
                                   size="30" maxlength="50" class="form-control">
                        </div>                        
                        
                        <div class="col-md-3">
                            <label>
                                <?php if ($bFamilyAddress2) {
        //echo '<span style="color: red;">';
    }

    //echo gettext('Address').' 2: (Casa ou Apto)';
   
    echo 'Complemento (casa, apto e etc):';


    if ($bFamilyAddress2) {
        echo '</span>';
    } ?>
                            </label>
                            <input type="text" id="Address2" name="Address2"
                                   value="<?= htmlentities(stripslashes($sAddress2), ENT_NOQUOTES, 'UTF-8') ?>"
                                   size="30" maxlength="50" class="form-control">
                        </div>
                        
                    </div>
                </div>
                <p/>
                <div class="row">
                    
						<div class="col-md-2">
                            <label>
                                <?php if ($bFamilyBairro) {

    }

    echo gettext('Bairro').':';

    if ($bFamilyBairro) {
        echo '</span>';
    } ?>
                            </label>
                            <?php echo '<input type="text" id="Bairro" name="Bairro" 
                                   value="'.$sBairro.'"
                                    size="30" maxlength="50" class="form-control">' ?>
                                    
                            <?php //require 'Include/BairroDropDown.php'; ?>
                        </div>
                        
						<div class="col-md-2">
                            <label>
                                <?php if ($bFamilyCity) {
        //echo '<span style="color: red;">';
    }

    echo gettext('City').':';

    if ($bFamilyCity) {
        echo '</span>';
    } ?>
                            </label>
                            <input type="text" id="City" name="City"
                                   value="<?= htmlentities(stripslashes($sCity), ENT_NOQUOTES, 'UTF-8') ?>"
                                   class="form-control">
                        </div>
                        
							<div class="form-group col-md-2">
                        <label for="State">
                            <?php if ($bFamilyState) {
        //echo '<span style="color: red;">';
    }

    echo gettext('State').':';

    if ($bFamilyState) {
        echo '</span>';
    } ?>
                        </label>
                        <?php require 'Include/StateDropDown.php'; ?>
                    </div>
                    
                    
                    <div class="form-group col-md-2">
                        <label for="Zip">
                            <?php if ($bFamilyCountry) {
        //echo '<span style="color: red;">';
    }

    echo gettext('Country').':';

    if ($bFamilyCountry) {
        echo '</span>';
    } ?>
                        </label>
                        <?php require 'Include/CountryDropDown.php'; ?>
                    </div>
                </div>
                <p/>
            <?php

} else { // put the current values in hidden controls so they are not lost if hiding the person-specific info?>
                <input type="hidden" name="Address1"
                       value="<?= htmlentities(stripslashes($sAddress1), ENT_NOQUOTES, 'UTF-8') ?>"></input>
					 <input type="hidden" name="Numero"
                       value="<?= htmlentities(stripslashes(substr($sAddress1, strpos($data, ",") + 1)), ENT_NOQUOTES, 'UTF-8') ?>"></input>                       
                <input type="hidden" name="Address2"
                       value="<?= htmlentities(stripslashes($sAddress2), ENT_NOQUOTES, 'UTF-8') ?>"></input>
                <input type="hidden" name="Bairro"
                       value="<?= htmlentities(stripslashes($sBairro), ENT_NOQUOTES, 'UTF-8') ?>"></input>
                <input type="hidden" name="City"
                       value="<?= htmlentities(stripslashes($sCity), ENT_NOQUOTES, 'UTF-8') ?>"></input>
                <input type="hidden" name="State"
                       value="<?= htmlentities(stripslashes($sState), ENT_NOQUOTES, 'UTF-8') ?>"></input>
                <input type="hidden" name="Zip"
                       value="<?= htmlentities(stripslashes($sZip), ENT_NOQUOTES, 'UTF-8') ?>"></input>
                <input type="hidden" name="Country"
                       value="<?= htmlentities(stripslashes($sCountry), ENT_NOQUOTES, 'UTF-8') ?>"></input>
            <?php

} ?>
            <div class="row">
                <div class="form-group col-md-3">
                    <label for="HomePhone">
                        <?php
                        if ($bFamilyHomePhone) {
                            echo '<span ">'.gettext('Home Phone').':</span>';
                        } else {
                            echo gettext('Home Phone').':';
                        }
                        ?>
                    </label>
                    <div class="input-group">
                        <div class="input-group-addon">
                            <i class="fa fa-phone"></i>
                        </div>
                        <input type="text" name="HomePhone"
                               value="<?= htmlentities(stripslashes($sHomePhone), ENT_NOQUOTES, 'UTF-8') ?>" size="30"
                               maxlength="30" class="form-control" data-inputmask='"mask": "<?= SystemConfig::getValue('sHomePhoneFormat')?>"' data-mask>
                        <br><input type="checkbox" name="NoFormat_HomePhone"
                                   value="1" <?php if ($bNoFormat_HomePhone) {
                            echo ' checked';
                        } ?>><?= gettext('Do not auto-format') ?>
                    </div>
                </div>
                <div class="form-group col-md-3">
                    <label for="WorkPhone">
                        <?php
                        if ($bFamilyWorkPhone) {
                            echo '<span ">'.gettext('Work Phone').':</span>';
                        } else {
                            echo gettext('Work Phone').':';
                        }
                        ?>
                    </label>
                    <div class="input-group">
                        <div class="input-group-addon">
                            <i class="fa fa-phone"></i>
                        </div>
                        <input type="text" name="WorkPhone"
                               value="<?= htmlentities(stripslashes($sWorkPhone), ENT_NOQUOTES, 'UTF-8') ?>" size="30"
                               maxlength="30" class="form-control"
                               data-inputmask='"mask": "<?= SystemConfig::getValue('sPhoneFormatWithExt')?>"' data-mask/>
                        <br><input type="checkbox" name="NoFormat_WorkPhone"
                                   value="1" <?php if ($bNoFormat_WorkPhone) {
                            echo ' checked';
                        } ?>><?= gettext('Do not auto-format') ?>
                    </div>
                </div>

                <div class="form-group col-md-3">
                    <label for="CellPhone">
                        <?php
                        if ($bFamilyCellPhone) {
                            echo '<span ">'.gettext('Mobile Phone').':</span>';
                        } else {
                            echo gettext('Mobile Phone').':';
                        }
                        ?>
                    </label>
                    <div class="input-group">
                        <div class="input-group-addon">
                            <i class="fa fa-phone"></i>
                        </div>
                        <input type="text" name="CellPhone"
                               value="<?= htmlentities(stripslashes($sCellPhone), ENT_NOQUOTES, 'UTF-8') ?>" size="30"
                               maxlength="30" class="form-control" data-inputmask='"mask": "<?= SystemConfig::getValue('sPhoneFormat')?>"' data-mask>
                        <br><input type="checkbox" name="NoFormat_CellPhone"
                                   value="1" <?php if ($bNoFormat_CellPhone) {
                            echo ' checked';
                        } ?>><?= gettext('Do not auto-format') ?>
                    </div>
                </div>
            </div>
            <p/>
            <div class="row">
                <div class="form-group col-md-4">
                    <label for="Email">
                        <?php
                        if ($bFamilyEmail) {
                            echo '<span ">'.gettext('Email').':</span></td>';
                        } else {
                            echo gettext('Email').':</td>';
                        }
                        ?>
                    </label>
                    <div class="input-group">
                        <div class="input-group-addon">
                            <i class="fa fa-envelope"></i>
                        </div>
                        <input type="text" name="Email"
                               value="<?= htmlentities(stripslashes($sEmail), ENT_NOQUOTES, 'UTF-8') ?>" size="30"
                               maxlength="100" class="form-control">
                        <?php if ($sEmailError) {
                            ?><font color="red"><?php echo $sEmailError ?></font><?php

                        } ?>
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <label for="WorkEmail"><?= gettext('Work / Other Email') ?>:</label>
                    <div class="input-group">
                        <div class="input-group-addon">
                            <i class="fa fa-envelope"></i>
                        </div>
                        <input type="text" name="WorkEmail"
                               value="<?= htmlentities(stripslashes($sWorkEmail), ENT_NOQUOTES, 'UTF-8') ?>" size="30"
                               maxlength="100" class="form-control">
                        <?php if ($sWorkEmailError) {
                            ?><font
                            color="red"><?php echo $sWorkEmailError ?></font></td><?php

                        } ?>
                    </div>
                </div>
            </div>
        </div>
        
        
    </div>

<?php if ($numCustomFields > 0) {
                            ?>
    <div class="box box-info clearfix">
        <div class="box-header">
            <h3 class="box-title"><?= gettext('Informações Adicionais') ?></h3>
            <!-- <div class="pull-right"><br/>
                <input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="PersonSubmit">
            </div> -->
        </div><!-- /.box-header -->
        <div class="box-body">
            <?php if ($numCustomFields > 0) {
                                mysqli_data_seek($rsCustomFields, 0);

                                while ($rowCustomField = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH)) {
                                    extract($rowCustomField);

                                    if ($aSecurityType[$custom_FieldSec] == 'bAll' || $_SESSION[$aSecurityType[$custom_FieldSec]]) {
                                        echo "<div class=\"form-group col-md-3\"><label>".$custom_Name.'</label>';

                                        if (array_key_exists($custom_Field, $aCustomData)) {
                                            $currentFieldData = trim($aCustomData[$custom_Field]);
                                        } else {
                                            $currentFieldData = '';
                                        }

                                        if ($type_ID == 11) {
                                            $custom_Special = $sPhoneCountry;
                                        }

                                        formCustomField($type_ID, $custom_Field, $currentFieldData, $custom_Special, !isset($_POST['PersonSubmit']));
                                        if (isset($aCustomErrors[$custom_Field])) {
                                            echo '<span style="color: red; ">'.$aCustomErrors[$custom_Field].'</span>';
                                        }
                                        echo '</div>';
                                    }
                                }
                            } ?>
        </div>
    </div>
  <?php

                        } ?>    
    
    <div class="box box-info clearfix">
        <div class="box-header">
            <h3 class="box-title"><?= gettext('Informações de Membresia') ?></h3>
            <!-- <div class="pull-right"><br/>
                <input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="PersonSubmit">
            </div> -->
        </div><!-- /.box-header -->
        <div class="box-body">
            <div class="row">
              <div class="form-group col-md-3 col-lg-3">
                <label><?= gettext('Classificação') ?>:</label>
                <select name="Classification" class="form-control">
                  <option value="0"><?= gettext('Unassigned') ?></option>
                  <option value="0" disabled>-----------------------</option>
                  <?php while ($aRow = mysqli_fetch_array($rsClassifications)) {
                            extract($aRow);
                            echo '<option value="'.$lst_OptionID.'"';
                            if ($iClassification == $lst_OptionID) {
                                echo ' selected';
                            }
                            echo '>'.$lst_OptionName.'&nbsp;';
                        } ?>
                </select>
              </div>
                <div class="form-group col-md-3 col-lg-3">
                    <label><?= gettext('Data da Membresia') ?>:</label>
                    <div class="input-group">
                        <div class="input-group-addon">
                            <i class="fa fa-calendar"></i>
                        </div>
                        <input type="text" name="MembershipDate" class="form-control date-picker"
                               value="<?= $dMembershipDate ?>" maxlength="10" id="sel1" size="11"
                               placeholder="YYYY-MM-DD">
                        <?php if ($sMembershipDateError) {
                            ?><font
                            color="red"><?= $sMembershipDateError ?></font><?php

                        } ?>
                    </div>
                </div>
               </div>
               
               <br><br>
                 <div class="row">
                    <div class="col-md-3">
                        <label><?= gettext('Diácono') ?></label><br/>
                        <input type="checkbox" name="Diacono" id="Diacono" value="1" <?php if ($iDiacono) {
    echo ' checked';
} ?> /> <br><br><br>
                    </div>   
                    
                    <div class="form-group col-md-3 col-lg-3" id="DiaconoEleito" class="form-control date-picker" >
                    <label><?= gettext('Data Eleito Diácono') ?>:</label>
                    <div class="input-group">
                        <div class="input-group-addon">
                            <i class="fa fa-calendar"></i>
                        </div>
                        <input type="text" name="DiaconoDate" class="form-control date-picker"
                               value="<?= $dDiaconoDate ?>" maxlength="10" id="sel1" size="11"
                               placeholder="YYYY-MM-DD">
                        <?php if ($sMembershipDateError) {
                            ?><font
                            color="red"><?= $sMembershipDateError ?></font><?php

                        } ?>
                    </div>
                </div>
                 </div>
                                 <div class="row">
                <div class="col-md-3">
                        <label><?= gettext('Presbítero') ?></label><br/>
                        <input type="checkbox" name="Presbitero" id="Presbitero" value="1" <?php if ($iPresbitero) {
    echo ' checked';
} ?> /><br><br><br>
                    </div>   
                    
                    <div class="form-group col-md-3 col-lg-3" id="PresbiteroEleito" class="form-control date-picker" >
                    <label><?= gettext('Data Eleito Presbítero') ?>:</label>
                    <div class="input-group">
                        <div class="input-group-addon">
                            <i class="fa fa-calendar"></i>
                        </div>
                        <input type="text" name="PresbiteroDate" class="form-control date-picker"
                               value="<?= $dPresbiteroDate ?>" maxlength="10" id="sel1" size="11"
                               placeholder="YYYY-MM-DD">
                        <?php if ($sMembershipDateError) {
                            ?><font
                            color="red"><?= $sMembershipDateError ?></font><?php

                        } ?>
                    </div>
                </div>
                 
            </div>
         <?php    
            
            if ($iPersonID < 1) {
        // echo '</span>';
    ?>
 					<!--		<div class="col-md-3"> 
 								 <label><?= gettext('Sociedade Interna') ?></label><br/>   								                     
    								                       <select name="GroupAssignID" class="form-control">  -->
                    <?php while ($aRow = mysqli_fetch_array($rsGroups)) {
            extract($aRow);

                      //If the property doesn't already exist for this Person, write the <OPTION> tag
                      if (strlen(strstr($sAssignedGroups, ','.$grp_ID.',')) == 0) {
                        //  echo '<option value="'.$grp_ID.'">'.$grp_Name.'</option>';
                      }
        } ?> 
                <!--  </select> -->
                        
					           
            
        
       
       <?php }   ?>
       

        

       </div> </div>
        
 <!-- inicio do meu codigo de grupos yuri -->
 <?php if ($iPersonID > 0) {
    ?> 
  
       <div class="box box-info clearfix">
       
       <div class="box-header">
            <h3 class="box-title"><?= gettext('Sociedades Internas') ?></h3>
            </div><!-- /.box-header -->
        <div class="box-body">     


            <div class="main-box clearfix">
            <div class="main-box-body clearfix">
              <?php
              //Was anything returned?
              if (mysqli_num_rows($rsAssignedGroups) == 0) {
                  ?>
                <br>
                <div class="alert alert-warning">
                  <i class="fa fa-question-circle fa-fw fa-lg"></i> <span><?= gettext('Não atribuído a nenhuma sociedade interna') ?></span>
                </div>
              <?php
              } else {
                  echo '<div class="row">';
                // Loop through the rows
                while ($aRow = mysqli_fetch_array($rsAssignedGroups)) {
                    extract($aRow); ?>
                  <div class="col-md-3">
                    <p><br/></p>
                    <!-- Info box -->
                    <div class="box box-info">
                      <div class="box-header">
                        <h3 class="box-title"><a href="GroupView.php?GroupID=<?= $grp_ID ?>"><?= $grp_Name ?></a></h3>

                        <div class="box-tools pull-right">
                          <div class="label bg-aqua"><?= $roleName ?></div>
                        </div>
                      </div>
                      <?php
                      // If this group has associated special properties, display those with values and prop_PersonDisplay flag set.
                      if ($grp_hasSpecialProps) {
                          // Get the special properties for this group
                        $sSQL = 'SELECT groupprop_master.* FROM groupprop_master WHERE grp_ID = '.$grp_ID." AND prop_PersonDisplay = 'true' ORDER BY prop_ID";
                          $rsPropList = RunQuery($sSQL);
                          $sSQL = 'SELECT * FROM groupprop_'.$grp_ID.' WHERE per_ID = '.$iPersonID;
                          $rsPersonProps = RunQuery($sSQL);
                          $aPersonProps = mysqli_fetch_array($rsPersonProps, MYSQLI_BOTH);
                          echo '<div class="box-body">';
                          while ($aProps = mysqli_fetch_array($rsPropList)) {
                              extract($aProps);
                              $currentData = trim($aPersonProps[$prop_Field]);
                              if (strlen($currentData) > 0) {
                                  $sRowClass = AlternateRowStyle($sRowClass);
                                  if ($type_ID == 11) {
                                      $prop_Special = $sPhoneCountry;
                                  }
                                  echo '<strong>'.$prop_Name.'</strong>: '.displayCustomField($type_ID, $currentData, $prop_Special).'<br/>';
                              }
                          }
                          echo '</div><!-- /.box-body -->';
                      } ?>
                      <div class="box-footer">
                        <code>
                          <?php if ($_SESSION['bManageGroups']) {
                          ?>
                           <!-- <a href="GroupView.php?GroupID=<?= $grp_ID ?>" class="btn btn-default" role="button"><i class="glyphicon glyphicon-list"></i></a> -->
                            <div class="btn-group">
                              <button type="button" class="btn btn-default"><?= gettext('Editar') ?></button>
                              <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                                <span class="caret"></span>
                                <span class="sr-only">Toggle Dropdown</span>
                              </button>
                              <ul class="dropdown-menu" role="menu">
                                <li><a href="MemberRoleChange.php?GroupID=<?= $grp_ID ?>&PersonID=<?= $iPersonID ?>"><?= gettext('Alterar Função') ?></a></li>
                                <?php if ($grp_hasSpecialProps) {
                              ?>
                                  <li><a href="GroupPropsEditor.php?GroupID=<?= $grp_ID ?>&PersonID=<?= $iPersonID ?>"><?= gettext('Atualizar') ?></a></li>
                                <?php

                          } ?>
                              </ul>
                            </div> <br><br>
                            <a href="#" onclick="GroupRemove(<?= $grp_ID.', '.$iPersonID ?>);" class="btn btn-danger" role="button"><i class="fa fa-trash-o"></i> Remover desta Sociedade</a>
                          <?php

                      } ?>
                        </code>
                      </div>
                      <!-- /.box-footer-->
                    </div>
                    <!-- /.box -->
                  </div>
                  <?php
                  // NOTE: this method is crude.  Need to replace this with use of an array.
                  $sAssignedGroups .= $grp_ID.',';
                }
                  echo '</div>';
              }
    if ($_SESSION['bManageGroups']) {
        ?>
                       <div class="col-md-4">
<div class="alert alert-info">
                  <h4><strong><?php echo gettext('Atribuir a uma nova Sociedade Interna'); ?> </strong></h4>
                  <i class="fa fa-info-circle fa-fw fa-lg"></i> <span><?= gettext('Person will be assigned to the Group in the Default Role.') ?></span>

                  <p><br></p>
                  <select style="color:#000000" name="GroupAssignID">
                    <?php while ($aRow = mysqli_fetch_array($rsGroups)) {
            extract($aRow);

                      //If the property doesn't already exist for this Person, write the <OPTION> tag
                      if (strlen(strstr($sAssignedGroups, ','.$grp_ID.',')) == 0) {
                          echo '<option value="'.$grp_ID.'">'.$grp_Name.'</option>';
                      }
        } ?>
                  </select>
                  <a href="#" onclick="GroupAdd()" class="btn btn-success" role="button"><?= gettext('Assign User to Group') ?></a>
                  <br>
                </div>                </div>
              <?php
    } ?>
            </div>
          </div>   
          
        </div>
    </div>
   <?php }
    ?>

    
    <!-- fim do meu codigo de grupos yuri -->

    
   <div class="row">

            <div class="pull-right" style="padding: 20px; padding-right: 1%;">
  
    <input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="PersonSubmit">
    <?php if ($_SESSION['bAddRecords']) {
                            echo '<input type="submit" class="btn btn-primary" value="'.gettext('Save and Add').'" name="PersonSubmitAndAdd">';
                        } ?>
    <input type="button" class="btn btn-primary" value="<?= gettext('Cancel') ?>" name="PersonCancel"
           onclick="javascript:document.location='<?php if (strlen($iPersonID) > 0) {
                            echo 'PersonView.php?PersonID='.$iPersonID;
                        } else {
                            echo 'SelectList.php?mode=person';
                        } ?>';">
                        </div>                                               </div>                       
</form>


<script type="text/javascript">
	  var person_ID = <?= $iPersonID ?>;
	$(function() {
		$("[data-mask]").inputmask();
	});
	
function GroupAdd() {
    var GroupAssignID = $("select[name='GroupAssignID'] option:selected").val();
    $.ajax({
      method: "POST",
      url: window.CRM.root + "/api/groups/" + GroupAssignID + "/adduser/" + person_ID
    }).done(function (data) {
      location.reload();
    });
  }
  
  function GroupRemove(Group, Person) {
    var answer = confirm("<?= gettext('Tem certeza que deseja remover esta pessoa desta sociedade') ?>");
    if (answer)
      $.ajax({
        method: "POST",
        data:{"_METHOD":"DELETE"},
        url: window.CRM.root + "/api/groups/" + Group + "/removeuser/" + Person
      }).done(function (data) {
        location.reload();
      });
  }	
	
</script>


<script>

if (<?php echo ($iDiacono) ?>) {
   $("#DiaconoEleito").show();
}

else {
	   $("#DiaconoEleito").hide();
}

$("#Diacono").change(function () {
   $("#DiaconoEleito").toggle();
});

</script>

<script>
if (<?php echo ($iPresbitero) ?>) {
   $("#PresbiteroEleito").show();
}

else {
	   $("#PresbiteroEleito").hide();
}

$("#Presbitero").change(function () {
   $("#PresbiteroEleito").toggle();
});

</script>
<!-- INICIO - Preenchimento automático de endereço -->
<!-- Estamos usando um webservice EXTERNO! -->
<script type="text/javascript">
		$("#Zip").focusout(function(){
			//Início do Comando AJAX
			$.ajax({
				//O campo URL diz o caminho de onde virá os dados
				//É importante concatenar o valor digitado no CEP
				url: 'https://viacep.com.br/ws/'+$(this).val()+'/json/unicode/',
				//Aqui você deve preencher o tipo de dados que será lido,
				//no caso, estamos lendo JSON.
				dataType: 'json',
				//SUCESS é referente a função que será executada caso
				//ele consiga ler a fonte de dados com sucesso.
				//O parâmetro dentro da função se refere ao nome da variável
				//que você vai dar para ler esse objeto.
				success: function(resposta){
					//Agora basta definir os valores que você deseja preencher
					//automaticamente nos campos acima.
					$("#Address1").val(resposta.logradouro);
					$("#Bairro").val(resposta.bairro);
					$("#City").val(resposta.localidade);
					$("#state-input").val(resposta.uf);
					//Vamos incluir para que o Número seja focado automaticamente
					//melhorando a experiência do usuário
					$("#Numero").focus();
				}
			});
		});
</script>
<!-- FIM - Preenchimento automático de endereço -->	

<?php require 'Include/Footer.php' ?>
