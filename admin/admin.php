<?php
/**
 * @author   Simafri  https://simafri.com
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include substr($tmp, 0, ($i+1))."/main.inc.php";
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) $res=@include dirname(substr($tmp, 0, ($i+1)))."/main.inc.php";
// Try main.inc.php using relative path
if (! $res && file_exists("../../main.inc.php")) $res=@include "../../main.inc.php";
if (! $res && file_exists("../../../main.inc.php")) $res=@include "../../../main.inc.php";
if (! $res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

// Load translation files required by the page
$langs->load("admin");

if (!$user->admin) accessforbidden();
$action = GETPOST('action');

/*
 * Action
 */
if ($action == 'set')
{
	//Enable the cron jobs
	$sql = "UPDATE ".MAIN_DB_PREFIX."cronjob SET status = 1 WHERE methodename='lancer_dolibackup' OR methodename='controle_dolibackup'";
	$db->query($sql);
	// Set frequency of backup, updates the cronjob table
	if (GETPOST('FREQUENCE_BACKUP')){
		$sql = "UPDATE ".MAIN_DB_PREFIX."cronjob SET frequency = ".GETPOST('FREQUENCE_BACKUP')." WHERE methodename = 'lancer_dolibackup'";
		$db->query($sql);
	}
	dolibarr_set_const($db, 'FREQUENCE_BACKUP', GETPOST('FREQUENCE_BACKUP'), 'chaine', 0, '', $conf->entity);

	// Set the start time of the backups, updates the cronjob table

	$sql = "SELECT datenextrun FROM ".MAIN_DB_PREFIX."cronjob WHERE methodename = 'lancer_dolibackup'";

	$resql = $db->query($sql);
	if ($resql){
		$datenextrun = $db->fetch_object($resql)->datenextrun;
	}
	if ($datenextrun){
		$heure_lancement = dol_mktime(GETPOST('HEURE_LANCEMENThour', 'int'), GETPOST('HEURE_LANCEMENTmin', 'int'), 0, dol_print_date($datenextrun,"%m"), dol_print_date($datenextrun,"%d"), dol_print_date($datenextrun,"%Y"));	
	}
	else {
		$heure_lancement = dol_mktime(GETPOST('HEURE_LANCEMENThour', 'int'), GETPOST('HEURE_LANCEMENTmin', 'int'), 0, dol_print_date(dol_now(),"%m"), dol_print_date(dol_now(),"%d"), dol_print_date(dol_now(),"%Y"));
	}
	if ($heure_lancement <= dol_now()){
		$heure_lancement = strtotime("+1 days", $heure_lancement);
	}

	
	$sql = "UPDATE ".MAIN_DB_PREFIX."cronjob SET datenextrun = '".$db->idate($heure_lancement)."' WHERE methodename = 'lancer_dolibackup'";
	$db->query($sql);

	dolibarr_set_const($db, 'HEURE_LANCEMENT', $heure_lancement, 'chaine', 0, '', $conf->entity);
	
	//Sets FTP credentials and email addresses to receive errors into const table

	dolibarr_set_const($db, 'UTILISATEUR_FTP', GETPOST('UTILISATEUR_FTP'), 'chaine', 0, '', $conf->entity);
	
	dolibarr_set_const($db, 'MOT_DE_PASSE_FTP', GETPOST('MOT_DE_PASSE_FTP'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SERVEUR_FTP', GETPOST('SERVEUR_FTP'), 'chaine', 0, '', $conf->entity);

	dolibarr_set_const($db, 'EMAILS_ECHEC', GETPOST('EMAILS_ECHEC'), 'chaine', 0, '', $conf->entity);
	
	
	if (!$error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
}



$langs->load('dolibackup@dolibackup');

/*
 * View
 */

$help_url = '';
llxHeader('', $langs->trans("DolibackupSetup"), $help_url);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("DolibackupSetup"), $linkback, 'title_setup');
print '<br>';



print '<form action="'.$_SERVER["PHP_SELF"].'" method="post">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Description").'</td>';
print '<td  width="70%">'.$langs->trans("Value").'</td>'."\n";
print '</tr>';

$form = new Form($db);


print '<tr class="oddeven">';
print '<td>'.$langs->trans("FrequenceBackup").'</td>';
print '<td  width="70%">';
print '<input type="text" value="'.$conf->global->FREQUENCE_BACKUP.'" name="FREQUENCE_BACKUP" size="60">';
print '</td></tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("HeureLancement").'</td>';
print '<td  width="70%">';
print $form->selectDate($conf->global->HEURE_LANCEMENT, 'HEURE_LANCEMENT', 1, 1, '', "", 0);
print '</td></tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("ServeurFTP").'</td>';
print '<td  width="70%">';
print '<input type="text" value="'.$conf->global->SERVEUR_FTP.'" name="SERVEUR_FTP" size="60">';
print '</td></tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("UtilisateurFTP").'</td>';
print '<td  width="70%">';
print '<input type="text" value="'.$conf->global->UTILISATEUR_FTP.'" name="UTILISATEUR_FTP" size="60">';
print '</td></tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("MotdepasseFTP").'</td>';
print '<td  width="70%">';
print '<input type="password" value="'.$conf->global->MOT_DE_PASSE_FTP.'" name="MOT_DE_PASSE_FTP" size="60">';
print '</td></tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("EmailsEchec").'</td>';
print '<td  width="70%">';
print '<input type="text" value="'.$conf->global->EMAILS_ECHEC.'" name="EMAILS_ECHEC" size="60">';
print '</td></tr>';


print '</table>';
print '<div class="center" ><input class="button" type="submit" value="'.$langs->trans('Save').'"></div>';
print '</form>';

// End of page
llxFooter();
$db->close();
