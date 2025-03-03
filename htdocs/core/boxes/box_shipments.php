<?php
/* Copyright (C) 2003-2007 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2009 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2019      Alexandre Spangaro   <aspangaro@open-dsi.fr>
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
 *
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

/**
 *		\file       htdocs/core/boxes/box_shipments.php
 *		\ingroup    shipment
 *		\brief      Module for generating the display of the shipment box
 */

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';


/**
 * Class to manage the box to show last shipments
 */
class box_shipments extends ModeleBoxes
{
	public $boxcode = "lastcustomershipments";
	public $boximg = "dolly";
	public $boxlabel = "BoxLastCustomerShipments";
	public $depends = array("expedition");

	/**
	 *  Constructor
	 *
	 *  @param  DoliDB  $db         Database handler
	 *  @param  string  $param      More parameters
	 */
	public function __construct($db, $param)
	{
		global $user;

		$this->db = $db;

		$this->hidden = !$user->hasRight('expedition', 'lire');
	}

	/**
	 *  Load data for box to show them later
	 *
	 *  @param	int		$max        Maximum number of records to load
	 *  @return	void
	 */
	public function loadBox($max = 5)
	{
		global $user, $langs;
		$langs->loadLangs(array('orders', 'sendings'));

		$this->max = $max;

		include_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
		include_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
		include_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

		$shipmentstatic = new Expedition($this->db);
		$orderstatic = new Commande($this->db);
		$societestatic = new Societe($this->db);

		$this->info_box_head = array(
			'text' => $langs->trans("BoxTitleLastCustomerShipments", $max).'<a class="paddingleft" href="'.DOL_URL_ROOT.'/expedition/list.php?sortfield=e.tms&sortorder=DESC"><span class="badge">...</span></a>'
		);

		if ($user->hasRight('expedition', 'lire')) {
			$sql = "SELECT s.rowid as socid, s.nom as name, s.name_alias";
			$sql .= ", s.code_client, s.code_compta, s.client";
			$sql .= ", s.logo, s.email, s.entity";
			$sql .= ", e.ref, e.tms";
			$sql .= ", e.rowid";
			$sql .= ", e.ref_customer";
			$sql .= ", e.fk_statut";
			$sql .= ", e.fk_user_valid";
			$sql .= ", c.ref as commande_ref";
			$sql .= ", c.rowid as commande_id";
			$sql .= " FROM ".MAIN_DB_PREFIX."expedition as e";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."element_element as el ON e.rowid = el.fk_target AND el.targettype = 'shipping' AND el.sourcetype IN ('commande')";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."commande as c ON el.fk_source = c.rowid AND el.sourcetype IN ('commande') AND el.targettype = 'shipping'";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = e.fk_soc";
			if (!$user->hasRight('societe', 'client', 'voir')) {
				$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON e.fk_soc = sc.fk_soc";
			}
			$sql .= " WHERE e.entity IN (".getEntity('expedition').")";
			if (getDolGlobalString('ORDER_BOX_LAST_SHIPMENTS_VALIDATED_ONLY')) {
				$sql .= " AND e.fk_statut = 1";
			}
			if ($user->socid > 0) {
				$sql .= " AND s.rowid = ".((int) $user->socid);
			}
			if (!$user->hasRight('societe', 'client', 'voir')) {
				$sql .= " AND sc.fk_user = ".((int) $user->id);
			} else {
				$sql .= " ORDER BY e.tms DESC, e.date_delivery DESC, e.ref DESC";
			}
			$sql .= $this->db->plimit($max, 0);

			$result = $this->db->query($sql);
			if ($result) {
				$num = $this->db->num_rows($result);

				$line = 0;

				while ($line < $num) {
					$objp = $this->db->fetch_object($result);

					$shipmentstatic->id = $objp->rowid;
					$shipmentstatic->ref = $objp->ref;
					$shipmentstatic->ref_customer = $objp->ref_customer;

					$orderstatic->id = $objp->commande_id;
					$orderstatic->ref = $objp->commande_ref;

					$societestatic->id = $objp->socid;
					$societestatic->name = $objp->name;
					//$societestatic->name_alias = $objp->name_alias;
					$societestatic->code_client = $objp->code_client;
					$societestatic->code_compta = $objp->code_compta;
					$societestatic->code_compta_client = $objp->code_compta;
					$societestatic->client = $objp->client;
					$societestatic->logo = $objp->logo;
					$societestatic->email = $objp->email;
					$societestatic->entity = $objp->entity;

					$this->info_box_contents[$line][] = array(
						'td' => 'class="nowraponall"',
						'text' => $shipmentstatic->getNomUrl(1),
						'asis' => 1,
					);

					$this->info_box_contents[$line][] = array(
						'td' => 'class="tdoverflowmax150 maxwidth150onsmartphone"',
						'text' => $societestatic->getNomUrl(1),
						'asis' => 1,
					);

					$this->info_box_contents[$line][] = array(
						'td' => 'class="nowraponall"',
						'text' => ($orderstatic->id > 0 ? $orderstatic->getNomUrl(1) : ''),
						'asis' => 1,
					);

					$this->info_box_contents[$line][] = array(
						'td' => 'class="right" width="18"',
						'text' => $shipmentstatic->LibStatut($objp->fk_statut, 3),
					);

					$line++;
				}

				if ($num == 0) {
					$this->info_box_contents[$line][0] = array(
					'td' => 'class="center"',
						'text' => '<span class="opacitymedium">'.$langs->trans("NoRecordedShipments").'</span>'
					);
				}

				$this->db->free($result);
			} else {
				$this->info_box_contents[0][0] = array(
					'td' => '',
					'maxlength' => 500,
					'text' => ($this->db->error().' sql='.$sql),
				);
			}
		} else {
			$this->info_box_contents[0][0] = array(
				'td' => 'class="nohover left"',
				'text' => '<span class="opacitymedium">'.$langs->trans("ReadPermissionNotAllowed").'</span>'
			);
		}
	}



	/**
	 *	Method to show box.  Called when the box needs to be displayed.
	 *
	 *	@param	?array<array{text?:string,sublink?:string,subtext?:string,subpicto?:?string,picto?:string,nbcol?:int,limit?:int,subclass?:string,graph?:int<0,1>,target?:string}>   $head       Array with properties of box title
	 *	@param	?array<array{tr?:string,td?:string,target?:string,text?:string,text2?:string,textnoformat?:string,tooltip?:string,logo?:string,url?:string,maxlength?:int,asis?:int<0,1>}>   $contents   Array with properties of box lines
	 *	@param	int<0,1>	$nooutput	No print, only return string
	 *	@return	string
	 */
	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}
}
