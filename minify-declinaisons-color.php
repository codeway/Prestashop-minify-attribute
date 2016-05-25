 <?php
	require(dirname(__FILE__).'/config/config.inc.php');

	error_reporting(E_ALL);
	ini_set("display_errors", 1);

    $default_language = Configuration::get('PS_LANG_DEFAULT');
	$attributesgrouptominify = 'couleur-%';
	// NOUVEAU GROUPE QUI DEVRA CONTENIR LES ATTRIBUTS
	$attributeGroupeColorId = (int)6620;

	// Get products
	$sql = "SELECT *, al.name as alname, agl.name as aglname
			FROM "._DB_PREFIX_."attribute a, "._DB_PREFIX_."attribute_lang al, "._DB_PREFIX_."attribute_group ag, "._DB_PREFIX_."attribute_group_lang agl
			WHERE a.id_attribute = al.id_attribute 
			AND a.id_attribute_group = ag.id_attribute_group
			AND ag.id_attribute_group = agl.id_attribute_group
			AND agl.name like '".$attributesgrouptominify."' ";
	$attributes = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
	vardump(count($attributes));

	// On test tous les attributs
	foreach ( $attributes as $attribute ) {
		// Check if color already exist in new group
		$sql = "
			SELECT *, al.name as alname
			FROM "._DB_PREFIX_."attribute a, "._DB_PREFIX_."attribute_lang al
			WHERE a.id_attribute = al.id_attribute 
			AND id_attribute_group = ".$attributeGroupeColorId."
			AND al.name = '".pSQL($attribute['alname'])."'";
		$attributesAlreadyExist = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

		$newIdAttribute = null;

		// Si n'existe pas
		if(count($attributesAlreadyExist) == 0){
			vardump("add");
            $obj = new Attribute();
            // sets the proper id (corresponding to the right key)
            $obj->id_attribute_group = $attributeGroupeColorId;
            $obj->name[$default_language] = $attribute['alname'];
            $obj->position = Attribute::getHigherPosition($attributeGroupeColorId) + 1;
            $obj->add();
            $newIdAttribute = $obj->id;
		}else{
			vardump("link");
            $newIdAttribute = $attributesAlreadyExist[0]['id_attribute'];
		}

		// Une fois que l'on a le nouvel attribut id on remplace les anciens id par le nouveau
		if($newIdAttribute != null){
			$sqlUpdate = "UPDATE "._DB_PREFIX_."product_attribute_combination 
					SET id_attribute = '".$newIdAttribute."' 
					WHERE id_attribute = '".$attribute['id_attribute']."'";
			$res = Db::getInstance()->execute($sqlUpdate);
			// Si update ok, on supprime
			if($res === true){
				// Note on ne peut pas utiliser delete car se base sur combinaison ot déjà remplacé précedemment
				$sqlDelete = "DELETE FROM "._DB_PREFIX_."attribute 
						WHERE id_attribute = '".$attribute['id_attribute']."'";
				$res = Db::getInstance()->execute($sqlDelete);

				$sqlDelete = "DELETE FROM "._DB_PREFIX_."attribute_lang
						WHERE id_attribute = '".$attribute['id_attribute']."'";
				$res = Db::getInstance()->execute($sqlDelete);

				$sqlDelete = "DELETE FROM "._DB_PREFIX_."attribute_shop
						WHERE id_attribute = '".$attribute['id_attribute']."'";
				$res = Db::getInstance()->execute($sqlDelete);
			}
		}

	}

	// Ensuite on supprime tous les groupes d'attributs qui sont vides
	$sql = "
		SELECT count(a.id_attribute) as child, ag.id_attribute_group
		FROM  "._DB_PREFIX_."attribute_group ag LEFT JOIN "._DB_PREFIX_."attribute as a ON (a.id_attribute_group = ag.id_attribute_group)
		GROUP BY ag.id_attribute_group";
	$emptyGroup = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
	foreach ($emptyGroup as $infos) {
		if($infos["child"] == 0){
			$obj = new AttributeGroup($infos['id_attribute_group']);
			// Note on ne peut pas utiliser delete() car se base sur combinaison ot déjà remplacé précedemment
			$sqlDelete = "DELETE FROM "._DB_PREFIX_."attribute_group
					WHERE id_attribute_group = '".$infos['id_attribute_group']."'";
			$res = Db::getInstance()->execute($sqlDelete);

			$sqlDelete = "DELETE FROM "._DB_PREFIX_."attribute_group_lang
					WHERE id_attribute_group = '".$infos['id_attribute_group']."'";
			$res = Db::getInstance()->execute($sqlDelete);

			$sqlDelete = "DELETE FROM "._DB_PREFIX_."attribute_group_shop
					WHERE id_attribute_group = '".$infos['id_attribute_group']."'";
			$res = Db::getInstance()->execute($sqlDelete);
		}
	}

	die();

	function vardump($t){
		echo '<pre>';
		var_dump($t);
		echo '<pre>';
	}