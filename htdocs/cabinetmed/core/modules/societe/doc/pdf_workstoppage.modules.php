<?php
	/************************************************
	* Copyright (C) 2016-2022	Sylvain Legrand - <contact@infras.fr>	InfraS - <https://www.infras.fr>
	*
	* This program is free software: you can redistribute it and/or modify
	* it under the terms of the GNU General Public License as published by
	* the Free Software Foundation, either version 3 of the License, or
	* (at your option) any later version.
	*
	* This program is distributed in the hope that it will be useful,
	* but WITHOUT ANY WARRANTY; without even the implied warranty of
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	* GNU General Public License for more details.
	*
	* You should have received a copy of the GNU General Public License
	* along with this program.  If not, see <http://www.gnu.org/licenses/>.
	************************************************/

	/************************************************
	* 	\file		../cabinetmed/core/modules/societe/doc/pdf_workstoppage.modules.php
	* 	\ingroup	InfraS
	* 	\brief		Class file for work stoppage PDF
	************************************************/

	// Libraries ************************************
	require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
	dol_include_once('/cabinetmed/class/cabinetmedcons.class.php');
	dol_include_once('/cabinetmed/lib/cabinetmed.pdf.lib.php');


	/************************************************
	*	Class to generate PDF work stoppage InfraS
	************************************************/
	class pdf_workstoppage extends CommonDocGenerator
	{
		var $db;
		var $name;
		var $description;
		var $type;
		var $phpmin		= array(4,3,0); // Minimum version of PHP required by module
		var $version	= 'dolibarr';
		var $page_largeur;
		var $page_hauteur;
		var $format;
		var $marge_gauche;
		var	$marge_droite;
		var	$marge_haute;
		var	$marge_basse;
		var $emetteur;	// Objet societe qui emet
		// @var float X position for the columns
		public $posxdate;
		public $posxlabel;
		public $posxtotalamount;
		public $posxamountrule;
		public $posxbalance;

		/********************************************
		*	Constructor
		*
		*	@param		DoliDB		$db      Database handler
		********************************************/
		public function __construct($db)
		{
			global $conf, $langs, $mysoc;

			$langs->loadLangs(array('main', 'cabinemed@cabinemed'));

			pdf_CabinetMed_getValues($this);
			$this->name							= $langs->trans('WorkStoppagePDFName');
			$this->description					= $langs->trans('WorkStoppagePDFDescription');
			$this->option_logo					= 1;	// Affiche logo
			$this->option_tva					= 1;	// Gere option tva FACTURE_TVAOPTION
			$this->option_modereg				= 1;	// Affiche mode reglement
			$this->option_condreg				= 1;	// Affiche conditions reglement
			$this->option_codeproduitservice	= 1;	// Affiche code produit-service
			$this->option_multilang				= 1;	// Dispo en plusieurs langues
			$this->option_escompte				= 1;	// Affiche si il y a eu escompte
			$this->option_credit_note			= 1;	// Support credit notes
			$this->option_freetext				= 1;	// Support add of a personalised text
			$this->option_draft_watermark		= 1;	// Support add of a watermark on drafts
		}

		/********************************************
		*	Function to build pdf onto disk
		*
		*	@param		Object		$object				Object to generate
		*	@param		Translate	$outputlangs		Lang output object
		*	@param		string		$srctemplatepath	Full path of source filename for generator using a template file
		*	@param		int			$hidedetails		Do not show line details (inutilisée ! laissé pour la compatibilité)
		*	@param		int			$hidedesc			Do not show desc
		*	@param		int			$hideref			Do not show ref
		*	@return     int             				1=OK, 0=KO
		********************************************/
		public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
		{
			global $user, $langs, $conf, $mysoc, $db, $hookmanager;

			dol_syslog('write_file outputlangs->defaultlang = '.(is_object($outputlangs) ? $outputlangs->defaultlang : 'null'));
			if (! is_object($outputlangs))	$outputlangs					= $langs;
			// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
			if (!empty($this->use_fpdf))	$outputlangs->charset_output	= 'ISO-8859-1';
			$outputlangs->loadLangs(array('main', 'cabinemed@cabinemed'));
			if ($this->show_desc)			$hidedesc						= 0;
			$filesufixe						= '_TC';
			$idconsult						= GETPOST('idconsult', 'int');
			$baseDir						= !empty($conf->societe->multidir_output[$object->entity]) ? $conf->societe->multidir_output[$object->entity] : $conf->societe->dir_output;
			if ($baseDir) {
				$extrafields	= new ExtraFields($db);
				if ($idconsult > 0) {
					$outcome		= new CabinetmedCons($db);
					$result1		= $outcome->fetch($idconsult);
					$result1extra	= $outcome->fetch_optionals();
				}
				// Definition of $dir and $file
				if ($object->specimen) {
					$dir	= $conf->societe->dir_output;
					$file	= $dir.'/SPECIMEN.pdf';
				}
				else {
					$objectid			= dol_sanitizeFileName($object->id);
					$dir				= $baseDir.'/'.$objectid;
					$datefile			= dol_print_date($outcome->datecons, '%Y-%m-%d');
					$thirdparty_code	= $object->code_client;
					$file_name			= $langs->transnoentitiesnoconv('PrescriptionPDFFileName', $idconsult, $datefile);
					$file_name			= trim($file_name);
					$file				= $dir.'/'.dol_sanitizeFileName($file_name).'.pdf';
				}
				if (! file_exists($dir)) {
					if (dol_mkdir($dir) < 0) {
						$this->error	= $langs->transnoentities('ErrorCanNotCreateDir', $dir);
						return 0;
					}
				}
				if (file_exists($dir)) {
					if (! is_object($hookmanager)) {	// Add pdfgeneration hook
						include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
						$hookmanager	= new HookManager($this->db);
					}
					$hookmanager->initHooks(array('pdfgeneration'));
					$parameters			= array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
					global $action;
					$reshook			= $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action);    // Note that $action and $object may have been modified by some hooks
					// Create pdf instance
					$pdf				= pdf_getInstance($this->format);
					$default_font_size	= pdf_getPDFFontSize($outputlangs);	// Must be after pdf_getInstance
					$pdf->SetAutoPageBreak(1, 0);
					if (class_exists('TCPDF')) {
						$pdf->setPrintHeader(false);
						$pdf->setPrintFooter(false);
					}
					$pdf->SetFont(pdf_getPDFFont($outputlangs));
					// reduce the top margin before ol / il tag
					$tagvs							= array('p' => array(1 => array('h' => 0.0001, 'n' => 1)), 'ul' => array(0 => array('h' => 0.0001, 'n' => 1)));
					$pdf->setHtmlVSpace($tagvs);
					$pdf->Open();
					$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref).$filesufixe);
					$pdf->SetSubject($outputlangs->transnoentities('PdfPrescriptionTitle'));
					$pdf->SetCreator('Dolibarr '.DOL_VERSION);
					$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
					$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref).' '.$outputlangs->transnoentities('PdfPrescriptionTitle').' '.$outputlangs->convToOutputCharset($object->thirdparty->name));
					$pdf->setPageOrientation('', 1, 0);	// Edit the bottom margin of current page to set it.
					$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);   // Left, Top, Right
					if (empty($this->compression))	$pdf->SetCompression(false);
					// New page
					$pdf->AddPage();
					$pagenb							= 1;
					$pdf->MultiCell(0, 3, '');		// Set interline to 3
					// Define width and position
					$this->larg_util_txt			= $this->page_largeur - ($this->marge_gauche + $this->marge_droite + 2);
					$this->larg_util_cadre			= $this->page_largeur - ($this->marge_gauche + $this->marge_droite);
					$this->posx_G_txt				= $this->marge_gauche + 1;
					$this->tab_hl					= 4;
					$pdf->SetTextColor(0, 0, 60);
					$pdf->SetFont('','B', $default_font_size * 2);
					$posy							= $this->marge_haute;
					// Title
					$txt							= $outputlangs->transnoentities('PrescriptionTitle');
					$pdf->writeHTMLCell($this->larg_util_txt, $this->tab_hl, $this->posx_G_txt, $posy, $txt, 0, 1, false, true, 'C', true);
					$posy							= $pdf->getY() + ($this->tab_hl * 2);
					// Address and contact numbers
					$pdf->SetFont('','B', $default_font_size + 1);
					$address						= $this->emetteur->address.' '.$this->emetteur->zip.' '.$this->emetteur->town;
					$pdf->MultiCell($this->larg_util_txt, $this->tab_hl, $address, '', 'C', 0, 1, $this->posx_G_txt, $posy, true, 0, 0, false, 0, 'M', false);
					if (!empty($this->emetteur->phone))
						$emetphone	= $outputlangs->convToOutputCharset(dol_string_nohtmltag(dol_print_phone($this->emetteur->phone, $this->emetteur->country_code, 0, $this->emetteur->id, '', '&nbsp;')));
					if (!empty($this->emetteur->fax))
						$emetfax	= $outputlangs->convToOutputCharset(dol_string_nohtmltag(dol_print_phone($this->emetteur->fax, $this->emetteur->country_code, 0, $this->emetteur->id, '', '&nbsp;')));
					if (!empty($emetphone) || !empty($emetfax)) {
						$contacts	= !empty($emetphone) ? $outputlangs->transnoentities('PhoneShort').' : '.$emetphone : '';
						$contacts	.= (!empty($contacts) && !empty($emetfax) ? ' - ' : '').(!empty($emetfax) ? $outputlangs->transnoentities('Fax').' : '.$emetfax : '');
						$posy		+= $this->tab_hl;
						$pdf->MultiCell($this->larg_util_txt, $this->tab_hl, $contacts, '', 'C', 0, 1, $this->posx_G_txt, $posy, true, 0, 0, false, 0, 'M', false);
					}
					$posy			= $pdf->getY() + ($this->tab_hl * 2);
					$pdf->SetFont('','', $default_font_size);
					$pdf->SetTextColor(0, 0, 0);
					// User name
					$txtUser		= $outputlangs->convToOutputCharset($user->getFullName($outputlangs, 1));
					$pdf->MultiCell($this->larg_util_txt, $this->tab_hl, $txtUser, '', 'L', 0, 1, $this->posx_G_txt, $posy, true, 0, 0, false, 0, 'M', false);
					$posy			+= $this->tab_hl * 2;
					// RPPS and NAM
					$extrafields->fetch_name_optionals_label($user->table_element);
					$printable		= intval($extrafields->attributes[$user->table_element]['printable']['rpps']);
					$rppsvalue		= pdf_CabinetMed_formatNotes($user, $outputlangs, $extrafields->showOutputField('rpps', $user->array_options['options_rpps']));
					$rpps			= $printable == 1 || (!empty($rppsvalue) && $printable == 2) ? $rppsvalue : '';	// check if something is writting for this extrafield according to the extrafield management
					if (!empty($rpps)) {
						$txtrpps	= $outputlangs->transnoentities('rpps').' : '.$rpps;
						$pdf->MultiCell($this->larg_util_txt, $this->tab_hl, $txtrpps, '', 'L', 0, 1, $this->posx_G_txt, $posy, true, 0, 0, false, 0, 'M', false);
						$posy		+= $this->tab_hl;
						pdf_CabinetMed_writeBC($pdf, $rpps, array(0, 0, 0), $this->posx_G_txt, $posy, 45, $this->tab_hl * 3);
						$posy		+= $this->tab_hl * 4;
					}
					$printable	= intval($extrafields->attributes[$user->table_element]['printable']['nam']);
					$namvalue	= pdf_CabinetMed_formatNotes($user, $outputlangs, $extrafields->showOutputField('nam', $user->array_options['options_nam']));
					$nam		= $printable == 1 || (!empty($namvalue) && $printable == 2) ? $namvalue : '';	// check if something is writting for this extrafield according to the extrafield management
					if (!empty($nam)) {
						$txtnam	= $outputlangs->transnoentities('nam').' : '.$nam;
						$pdf->MultiCell($this->larg_util_txt, $this->tab_hl, $txtnam, '', 'L', 0, 1, $this->posx_G_txt, $posy, true, 0, 0, false, 0, 'M', false);
						$posy	+= $this->tab_hl;
						pdf_CabinetMed_writeBC($pdf, $nam, array(0, 0, 0), $this->posx_G_txt, $posy, 45, $this->tab_hl * 3);
						$posy	+= $this->tab_hl * 4;
					}
					// Email
					if ($user->email)
						$emetmail	= $outputlangs->convToOutputCharset(dol_string_nohtmltag(dol_print_email($user->email, 0, $user->id, '', 64, 1, 1)));
					if (!empty($emetmail)) {
						$email	= $outputlangs->transnoentities('Email').' : '.$emetmail;
						$pdf->MultiCell($this->larg_util_txt, $this->tab_hl, $email, '', 'L', 0, 1, $this->posx_G_txt, $posy, true, 0, 0, false, 0, 'M', false);
						$posy	+= ($this->tab_hl * 3);
					}
					// Customer name
					$extrafields->fetch_name_optionals_label($object->table_element);
					$printable		= intval($extrafields->attributes[$object->table_element]['printable']['birthdate']);
					$birthvalue		= pdf_CabinetMed_formatNotes($object, $outputlangs, $extrafields->showOutputField('birthdate', $object->array_options['options_birthdate']));
					$birthdate		= $printable == 1 || (!empty($birthvalue) && $printable == 2) ? $birthvalue : '';	// check if something is writting for this extrafield according to the extrafield management
					$txtName		= $outputlangs->convToOutputCharset($object->getFullName($outputlangs, 1)).' ('.$object->code_client.') ';
					$txtName		.= !empty($birthdate) ? ', '.$outputlangs->transnoentities('BirthDate').' : '.dol_print_date($birthdate, 'day', false, $outputlangs) : '';
					$pdf->MultiCell($this->larg_util_txt, $this->tab_hl, $txtName, '', 'L', 0, 1, $this->posx_G_txt, $posy, true, 0, 0, false, 0, 'M', false);
					$posy			+= $this->tab_hl * 2;
					// Town and date
					$txttown		= $this->emetteur->town.' '.$outputlangs->transnoentities('The').' '.dol_print_date($outcome->datecons, 'day', false, $outputlangs);
					$pdf->MultiCell($this->larg_util_txt, $this->tab_hl, $txttown, '', 'R', 0, 1, $this->posx_G_txt, $posy, true, 0, 0, false, 0, 'M', false);
					$posy			+= $this->tab_hl * 2;
					// treatment
					$treatmenttitle	= $outputlangs->transnoentities('TreatmentSugested');
					$pdf->MultiCell($this->larg_util_txt, $this->tab_hl, $treatmenttitle, '', 'L', 0, 1, $this->posx_G_txt, $posy, true, 0, 0, false, 0, 'M', false);
					$posy			+= $this->tab_hl * 2;
					$treatment		= $outcome->traitementprescrit;
					$pdf->MultiCell($this->larg_util_txt, $this->tab_hl, $treatment, '', 'L', 0, 1, $this->posx_G_txt, $posy, true, 0, 0, false, 0, 'M', false);
					$posy			+= $this->tab_hl * 4;
					// Signature
					if ($user->signature)
						$signature	= pdf_CabinetMed_formatNotes($user, $outputlangs, $user->signature);
					if (!empty($signature)) {
						$posy	+= ($this->tab_hl * 3);
						$pdf->writeHTMLCell($this->larg_util_txt, $this->tab_hl, $this->posx_G_txt, $posy, $signature, 0, 1, false, true, 'R', true);
					}
					if (method_exists($pdf, 'AliasNbPages'))	$pdf->AliasNbPages();
					$pdf->Close();
					$pdf->Output($file, 'F');
					// Add pdfgeneration hook
					$hookmanager->initHooks(array('pdfgeneration'));
					$parameters	= array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs, 'fromInfraS' => 1);
					global $action;
					$reshook	= $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action);    // Note that $action and $object may have been modified by some hooks
					if ($reshook < 0) {
						$this->error	= $hookmanager->error;
						$this->errors	= $hookmanager->errors;
					}
					if (!empty($this->main_umask))	@chmod($file, octdec($this->main_umask));
					$this->result					= array('fullpath' => $file);
					return 1;   // Pas d'erreur
				}
				else {
					$this->error	= $outputlangs->transnoentities('ErrorCanNotCreateDir', $dir);
					return 0;
				}
			}
			else {
				$this->error	= $outputlangs->transnoentities('ErrorConstantNotDefined', 'SOC_OUTPUTDIR');
				return 0;
			}
		}
	}
?>