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
	* 	\file		../cabinetmed/lib/cabinetmed.pdf.lib.php
	* 	\ingroup	InfraS
	* 	\brief		Set of functions used for CabinetMed PDF generation
	************************************************/

	// Libraries ************************************
	require_once DOL_DOCUMENT_ROOT.'/core/class/discount.class.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

	/************************************************
	*	Return array with format properties
	*
	*	@param	object		$template	Object we work on
	*	@return	void
	************************************************/
	function pdf_CabinetMed_getValues(&$template)
	{
		global $conf, $langs, $mysoc;

		$template->emetteur								= $mysoc;
		if (empty($template->emetteur->country_code))	$template->emetteur->country_code										= substr($langs->defaultlang, -2);
		$template->type									= 'pdf';
		$template->multilangs							= isset($conf->global->MAIN_MULTILANGS)					? $conf->global->MAIN_MULTILANGS				: 0;
		$template->use_fpdf								= isset($conf->global->MAIN_USE_FPDF)					? $conf->global->MAIN_USE_FPDF					: 0;
		$template->main_umask							= isset($conf->global->MAIN_UMASK)						? $conf->global->MAIN_UMASK						: '0755';
		$formatarray									= pdf_CabinetMed_getFormat();
		$template->page_largeur							= $formatarray['width'];
		$template->page_hauteur							= $formatarray['height'];
		$template->format								= array($template->page_largeur, $template->page_hauteur);
		$template->marge_gauche							= $conf->global->MAIN_PDF_MARGIN_LEFT >= 4				? $conf->global->MAIN_PDF_MARGIN_LEFT			: 20;
		$template->marge_haute							= $conf->global->MAIN_PDF_MARGIN_TOP >= 4				? $conf->global->MAIN_PDF_MARGIN_TOP			: 15;
		$template->marge_droite							= $conf->global->MAIN_PDF_MARGIN_RIGHT >= 4				? $conf->global->MAIN_PDF_MARGIN_RIGHT			: 20;
		$template->marge_basse							= $conf->global->MAIN_PDF_MARGIN_BOTTOM >= 4			? $conf->global->MAIN_PDF_MARGIN_BOTTOM			: 15;
		$template->formatpage							= array('largeur'	=> $template->page_largeur,	'hauteur'	=> $template->page_hauteur,	'mgauche'	=> $template->marge_gauche,
																'mdroite'	=> $template->marge_droite,	'mhaute'	=> $template->marge_haute,	'mbasse'	=> $template->marge_basse);
		$template->compression							= isset($conf->global->MAIN_DISABLE_PDF_COMPRESSION)	? $conf->global->MAIN_DISABLE_PDF_COMPRESSION	: 1;
	}

	/************************************************
	*	Return array with format properties
	*
	*	@param	string		$format			specific format to use
	*	@param	Translate	$outputlangs	Output lang to use to autodetect output format if setup not done
	*	@param	string		$mode			'setup' = Use setup, 'auto' = Force autodetection whatever is setup (this onkly if local $format is not used)
	*	@return	array						Array('width'=>w,'height'=>h,'unit'=>u);
	************************************************/
	function pdf_CabinetMed_getFormat($format = '', $outputlangs = null, $mode = 'setup')
	{
		global $conf, $db, $langs;

		dol_syslog('pdf_CabinetMed_getFormat Get paper format with mode = '.$mode.' MAIN_PDF_FORMAT = '.(empty($conf->global->MAIN_PDF_FORMAT) ? 'null' : $conf->global->MAIN_PDF_FORMAT).' outputlangs->defaultlang = '.(is_object($outputlangs) ? $outputlangs->defaultlang : 'null').' and langs->defaultlang = '.(is_object($langs) ? $langs->defaultlang : 'null'));
		// Default value if setup was not done and/or entry into c_paper_format not defined
		$width					= 210;
		$height					= 297;
		$unit					= 'mm';
		if (!empty($format))	$pdfformat	= $format;
		else					$pdfformat	= $mode == 'auto' || empty($conf->global->MAIN_PDF_FORMAT) || $conf->global->MAIN_PDF_FORMAT == 'auto' ? dol_getDefaultFormat($outputlangs) : $conf->global->MAIN_PDF_FORMAT;
		$sql	= 'SELECT code, label, width, height, unit FROM '.MAIN_DB_PREFIX.'c_paper_format';
		$sql	.= ' WHERE code = "'.$db->escape($pdfformat).'"';
		$resql	= $db->query($sql);
		if ($resql) {
			$obj	= $db->fetch_object($resql);
			if ($obj) {
				$width	= (int) $obj->width;
				$height	= (int) $obj->height;
				$unit	= $obj->unit;
			}
		}
		$db->free($resql);
		return array('width' => $width, 'height' => $height, 'unit' => $unit);
	}

	/********************************************
	*	Format notes with substitutions and right path for pictures
	*
	*	@param		Object		$object			Object shown in PDF
	*	@param		Translate	$outputlangs	Object lang for output
	*	@param		String		$notes			html string from data base
	*	@return		String						Return html string ready to print
	********************************************/
	function pdf_CabinetMed_formatNotes($object, $outputlangs, $notes)
	{
		global $dolibarr_main_url_root;

		$substitutionarray									= pdf_getSubstitutionArray($outputlangs, null, $object);
		complete_substitutions_array($substitutionarray, $outputlangs, $object);
		$html												= make_substitutions($notes, $substitutionarray, $outputlangs);
		// Clean variables not found
		$reg												= array();
		while (preg_match('/__(.+)_(.+)__/', $html, $reg))	$html	= str_replace($reg[0], '', $html);
		// the code below came from a Dolibarr v10 native function (convertBackOfficeMediasLinksToPublicLinks()) on functions2.lib.php
		$urlwithouturlroot									= preg_replace('/'.preg_quote(DOL_URL_ROOT, '/').'$/i', '', trim($dolibarr_main_url_root));	// Define $urlwithroot
		$urlwithroot										= $urlwithouturlroot.DOL_URL_ROOT;		// This is to use external domain name found into config file
		$html												= preg_replace('/src="[a-zA-Z0-9_\/\-\.]*(viewimage\.php\?modulepart=medias[^"]*)"/', 'src="'.$urlwithroot.'/\1"', preg_replace('#amp;#', '', $html));
		return $html;
	}

	/********************************************
	*	Output bar code (code 128)
	*
	*	@param	TCPDF		$pdf            The PDF factory
	*	@param	string		$BC				the string we print as bar code
	*	@param  Array		$bodytxtcolor	current text color
	*	@param	float		$posx			x position
	*	@param	float		$posy			y position
	*	@param	float		$width			bar code width
	*	@param	float		$height			bar code height (for 2D code such as Qr Code width = height and we change the x position to be on the middle of the width)
	* 	@return	Boolean						1 -> Ok ; <1 -> Ko
	 ********************************************/
	 function pdf_CabinetMed_writeBC(&$pdf, $BC, $bodytxtcolor, $posx, $posy, $width, $height)
	 {
		global $db;

		if (!empty($BC)) {
			$styleBC	= array('position'		=> '',
								'align'			=> 'C',
								'stretch'		=> false,
								'fitwidth'		=> true,
								'cellfitalign'	=> '',
								'border'		=> false,
								'hpadding'		=> '0',
								'vpadding'		=> '0',
								'fgcolor'		=> array($bodytxtcolor[0], $bodytxtcolor[1], $bodytxtcolor[2]),
								'bgcolor'		=> false,
								'text'			=> true,
								'label'			=> $BC,
								'font'			=> $pdf->getFontFamily(),
								'fontsize'		=> 8,
								'stretchtext'	=> 4
								);
			$pdf->write1DBarcode($BC, 'C128', $posx, $posy, $width, $height, 0.4, $styleBC, 'N');
			return 1;
		}
		else $pdf->writeHTMLCell(0, 0, $posx, $posy, $BC, 0, 1);
		return 0;
	 }
?>
