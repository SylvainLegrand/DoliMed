<?php
/* Copyright (C) 2003-2006 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (c) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 */

/**
 *  \file       htdocs/cabinetmed/stats/index.php
 *  \ingroup    facture
 *  \brief      Page of patient outcomes statistics
 *  \version    $Id: index.php,v 1.3 2011/06/14 23:39:01 eldy Exp $
 */


$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");
if (! $res && file_exists("../../../dolibarr/htdocs/main.inc.php")) $res=@include("../../../dolibarr/htdocs/main.inc.php");     // Used on dev env only
if (! $res && file_exists("../../../../dolibarr/htdocs/main.inc.php")) $res=@include("../../../../dolibarr/htdocs/main.inc.php");   // Used on dev env only
if (! $res && file_exists("../../../../../dolibarr/htdocs/main.inc.php")) $res=@include("../../../../../dolibarr/htdocs/main.inc.php");   // Used on dev env only
if (! $res) die("Include of main fails");
require_once(DOL_DOCUMENT_ROOT."/core/class/dolgraph.class.php");
dol_include_once("/cabinetmed/class/cabinetmedcons.class.php");
dol_include_once("/cabinetmed/class/cabinetmedstats.class.php");

$WIDTH=500;
$HEIGHT=200;

$userid=GETPOST('userid'); if ($userid < 0) $userid=0;
$socid=GETPOST('socid'); if ($socid < 0) $socid=0;
// Security check
if ($user->societe_id > 0)
{
    $action = '';
    $socid = $user->societe_id;
}

$year = strftime("%Y", time());
$startyear=$year-2;
$endyear=$year;

$mode=GETPOST("mode")?GETPOST("mode"):'customer';

if (empty($conf->cabinetmed->enabled))
accessforbidden();


/*
 * View
 */

$langs->load("cabinetmed@cabinetmed");

$form=new Form($db);

llxHeader();

$title=$langs->trans("Statistics");
$dir=$conf->cabinetmed->dir_temp;

print_fiche_titre($title, $mesg);

create_exdir($dir);

$stats = new CabinetMedStats($db, $socid, $mode, ($userid>0?$userid:0));


// Build graphic number of object
// $data = array(array('Lib',val1,val2,val3),...)
$data = $stats->getNbByMonthWithPrevYear($endyear,$startyear);
//var_dump($data);

$filenamenb = $dir."/outcomesnbinyear-".$year.".png";
$fileurlnb = DOL_URL_ROOT.'/viewimage.php?modulepart=cabinetmed_temp&amp;file=outcomesnbinyear-'.$year.'.png';

$px = new DolGraph();
$mesg = $px->isGraphKo();
if (! $mesg)
{
	$px->SetData($data);
	$px->SetPrecisionY(0);
	$i=$startyear;
	while ($i <= $endyear)
	{
		$legend[]=$i;
		$i++;
	}
	$px->SetLegend($legend);
	$px->SetMaxValue($px->GetCeilMaxValue());
	$px->SetWidth($WIDTH);
	$px->SetHeight($HEIGHT);
	$px->SetYLabel($langs->trans("Number"));
	$px->SetShading(3);
	$px->SetHorizTickIncrement(1);
	$px->SetPrecisionY(0);
	$px->mode='depth';
	$px->SetTitle($langs->trans("NumberByMonth"));

	$px->draw($filenamenb);
}

// Build graphic amount of object
$data = $stats->getAmountByMonthWithPrevYear($endyear,$startyear);
//var_dump($data);
// $data = array(array('Lib',val1,val2,val3),...)

$filenameamount = $dir."/outcomesamountinyear-".$year.".png";
$fileurlamount = DOL_URL_ROOT.'/viewimage.php?modulepart=cabinetmed_temp&amp;file=outcomesamountinyear-'.$year.'.png';

$px = new DolGraph();
$mesg = $px->isGraphKo();
if (! $mesg)
{
	$px->SetData($data);
	$i=$startyear;
	while ($i <= $endyear)
	{
		$legend[]=$i;
		$i++;
	}
	$px->SetLegend($legend);
	$px->SetMaxValue($px->GetCeilMaxValue());
	$px->SetMinValue(min(0,$px->GetFloorMinValue()));
	$px->SetWidth($WIDTH);
	$px->SetHeight($HEIGHT);
	$px->SetYLabel($langs->trans("Amount"));
	$px->SetShading(3);
	$px->SetHorizTickIncrement(1);
	$px->SetPrecisionY(0);
	$px->mode='depth';
	$px->SetTitle($langs->trans("AmountByMonth"));

	$px->draw($filenameamount);
}



print '<table class="notopnoleftnopadd" width="100%"><tr>';
print '<td align="center" valign="top">';

// Show filter box
print '<form name="stats" method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<table class="border" width="100%">';
print '<tr><td class="liste_titre" colspan="2">'.$langs->trans("Filter").'</td></tr>';
/*print '<tr><td>'.$langs->trans("ThirdParty").'</td><td>';
if ($mode == 'customer') $filter='s.client in (1,2,3)';
if ($mode == 'supplier') $filter='s.fournisseur = 1';
print $form->select_company($socid,'socid',$filter,1);
print '</td></tr>';
*/
print '<tr><td>'.$langs->trans("User").'</td><td>';
print $form->select_users($userid,'userid',1);
print '</td></tr>';
print '<tr><td align="center" colspan="2"><input type="submit" name="submit" class="button" value="'.$langs->trans("Refresh").'"></td></tr>';
print '</table>';
print '</form>';
print '<br><br>';

// Show array
$data = $stats->getAllByYear();

print '<table class="border" width="100%">';
print '<tr height="24">';
print '<td align="center">'.$langs->trans("Year").'</td>';
print '<td align="center">'.$langs->trans("Number").'</td>';
print '<td align="center">'.$langs->trans("AmountTotal").'</td>';
print '<td align="center">'.$langs->trans("AmountAverage").'</td>';
print '</tr>';

$oldyear=0;
foreach ($data as $val)
{
	$year = $val['year'];
	while ($year && $oldyear > $year+1)
	{	// If we have empty year
		$oldyear--;
		print '<tr height="24">';
		print '<td align="center"><a href="month.php?year='.$oldyear.'&amp;mode='.$mode.'">'.$oldyear.'</a></td>';
		print '<td align="right">0</td>';
		print '<td align="right">0</td>';
		print '<td align="right">0</td>';
		print '</tr>';
	}
	print '<tr height="24">';
	print '<td align="center">';
	//print '<a href="month.php?year='.$year.'&amp;mode='.$mode.'">';
	print $year;
	//print '</a>';
	print '</td>';
	print '<td align="right">'.$val['nb'].'</td>';
	print '<td align="right">'.price(price2num($val['total'],'MT'),1).'</td>';
	print '<td align="right">'.price(price2num($val['avg'],'MT'),1).'</td>';
	print '</tr>';
	$oldyear=$year;
}

print '</table>';


$db->close();

print '</td>';
print '<td align="center" valign="top">';

// Show graphs
print '<table class="border" width="100%"><tr valign="top"><td align="center">';
if ($mesg) { print $mesg; }
else {
	print '<img src="'.$fileurlnb.'" title="'.$langs->trans("Number").'" alt="'.$langs->trans("Number").'">';
	print "<br>\n";
	print '<img src="'.$fileurlamount.'" title="'.$langs->trans("Amount").'" alt="'.$langs->trans("Amount").'">';
}
print '</td></tr></table>';

print '</td></tr></table>';

llxFooter('$Date: 2011/06/14 23:39:01 $ - $Revision: 1.3 $');
?>
