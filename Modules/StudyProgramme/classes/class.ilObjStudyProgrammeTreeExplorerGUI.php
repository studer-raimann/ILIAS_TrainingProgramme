<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */
require_once("./Services/UIComponent/Explorer2/classes/class.ilTreeExplorerGUI.php");
require_once("./Services/UIComponent/AdvancedSelectionList/classes/class.ilAdvancedSelectionListGUI.php");
require_once("./Services/UIComponent/Glyph/classes/class.ilGlyphGUI.php");
require_once("./Services/UIComponent/Button/classes/class.ilLinkButton.php");
require_once("./Modules/StudyProgramme/classes/class.ilObjStudyProgrammeSettingsGUI.php");
/**
 * Class ilStudyProgrammeTreeGUI
 *
 * @author  Michael Herren <mh@studer-raimann.ch>
 * @version 1.0.0
 */
class ilObjStudyProgrammeTreeExplorerGUI extends ilExplorerBaseGUI {
	protected $js_study_programme_path = "./Modules/StudyProgramme/templates/js/ilStudyProgramme.js";
	protected $css_study_programme_path = "./Modules/StudyProgramme/templates/css/ilStudyProgramme.css";

	/**
	 * @var array
	 */
	//protected $stay_with_command = array( "", "render", "view", "infoScreen", "performPaste", "cut", "tree_view");

	/**
	 * @var int
	 */
	protected $tree_root_id;

	/**
	 * @var ilAccessHandler
	 */
	protected $access;
	/**
	 * @var ilLanguage
	 */
	protected $lng;

	/**
	 * @var ilTemplate
	 */
	protected $tpl;

	/**
	 * @var ilToolbarGUI
	 */
	protected $toolbar;

	/**
	 * @var ilCtrl
	 */
	protected $ctrl;

	protected $modal_id;

	protected $js_conf;

	/**
	 * @param $a_expl_id
	 * @param $a_parent_obj
	 * @param $a_parent_cmd
	 * @param $a_tree
	 */
	public function __construct($a_tree_root_id, $modal_id, $a_expl_id, $a_parent_obj, $a_parent_cmd) {
		global $ilAccess, $lng, $tpl, $ilToolbar, $ilCtrl;

		parent::__construct($a_expl_id, $a_parent_obj, $a_parent_cmd);

		$this->tree_root_id = $a_tree_root_id;

		$this->access = $ilAccess;
		$this->lng = $lng;
		$this->tpl = $tpl;
		$this->toolbar = $ilToolbar;
		$this->ctrl = $ilCtrl;
		$this->modal_id = $modal_id;
		$this->js_conf = array();

		$lng->loadLanguageModule("prg");

		$this->setAjax(true);

		if($this->checkAccess('write', $a_tree_root_id)) {
			$this->setEnableDnd(true);
		}
	}


	/**
	 * Return node element
	 *
	 * @param ilObjStudyProgramme $node
	 *
	 * @return string
	 */
	public function getNodeContent($node) {
		global $lng, $ilAccess;

		$node_classes = "title";
		$current_ref_id = (isset($_GET["ref_id"]))? $_GET["ref_id"] : -1;
		$current_node = ($node->getRefId() == $current_ref_id);
		$enable_delete = true;
		$is_study_programme = ($node instanceof ilObjStudyProgramme);

		// TODO: implement nicer way to create links for StudyProgrammes or LP-children

		if($is_study_programme){
			if ($current_node || $node->getRoot() == NULL) {
				$enable_delete = false;
			}

			if ($current_node) {
				$node_classes .= " ilHighlighted current_node";
			}
		} else {
			$node_classes .= " lp-object";
		}

		// TODO: move all the html into a template
		//$tpl = new ilTemplate()

		$data_line = '<span class="'.$node_classes.'">' . $node->getTitle() .'</span>';

		$data_line .= ($is_study_programme)? '<span class="points">('. $node->getPoints() ." ".$this->lng->txt('prg_points').')</span>' : '';

		if($this->checkAccess('write', $node->getRefId())) {
			$data_line .= '<span class="icon_bar">';
			if($is_study_programme) {
				$data_line .= (!$current_node)? $this->getActionLink('ilObjStudyProgrammeSettingsGUI', 'view', array('ref_id'=>$node->getRefId()), ilGlyphGUI::get(ilGlyphGUI::INFO)) : '';
				$data_line .= $this->getActionLink('ilObjStudyProgrammeTreeGUI', 'create', array('ref_id'=>$node->getRefId()), ilGlyphGUI::get(ilGlyphGUI::ADD));
			}

			if($enable_delete) {
				$data_line .= $this->getActionLink('ilObjStudyProgrammeTreeGUI', 'delete', array('ref_id'=>$node->getRefId(), 'item_ref_id'=>$current_ref_id), ilGlyphGUI::get(ilGlyphGUI::REMOVE));
			}
			$data_line .= '</span>';
		}

		return $data_line;
	}
	
	/**
	 * Generate link-element
	 *
	 * @param      $target_class
	 * @param      $cmd
	 * @param      $params  url-params send to the
	 * @param      $content
	 * @param bool $async
	 *
	 * @return string
	 */
	protected function getActionLink($target_class, $cmd, $params, $content, $async = true) {
		foreach($params as $param_name=>$param_value) {
			$this->ctrl->setParameterByClass($target_class, $param_name, $param_value);
		}

		$props = 'class="tree_button cmd_'.$cmd.'"';
		if($async) {
			$props .= ' data-toggle="modal" data-target="#'.$this->modal_id.'"';
		}

		return '<a href="'.$this->ctrl->getLinkTargetByClass($target_class, $cmd, '', true, false).'" '.$props.'>'.$content.'</a>';
	}



	/**
	 * @return mixed
	 */
	public function getRootNode() {
		$node = ilObjStudyProgramme::getInstanceByRefId($this->tree_root_id);
		if($node->getRoot() != NULL) {
			return $node->getRoot();
		}
		return $node;
	}

	/**
	 * Get node icon
	 * Return custom icon of OrgUnit type if existing
	 *
	 * @param array $a_node
	 *
	 * @return string
	 */
	public function getNodeIcon($a_node) {
		global $ilias;

		$obj_id = ilObject::_lookupObjId($a_node->getRefId());
		if ($ilias->getSetting('custom_icons')) {
			//TODO: implement custom icon functionality
		}

		return ilObject::_getIcon($obj_id, "tiny");
	}


	/**
	 * @param mixed $node
	 *
	 * @return string
	 */
	public function getNodeHref($node) {
		global $ilCtrl;

		if ($ilCtrl->getCmd() == "performPaste") {
			$ilCtrl->setParameterByClass("ilObjStudyProgrammeGUI", "target_node", $node->getRefId());
		}

		$ilCtrl->setParameterByClass("ilObjStudyProgrammeGUI", "ref_id", $node->getRefId());

		return $this->getLinkTarget();
	}


	/**
	 * @return string
	 */
	protected function getLinkTarget() {
		global $ilCtrl;

		/*if ($ilCtrl->getCmdClass() == "ilobjstudyprogrammegui" AND in_array($ilCtrl->getCmd(), $this->stay_with_command)) {
			return $ilCtrl->getLinkTargetByClass($ilCtrl->getCmdClass(), $ilCtrl->getCmd());
		} else {
			return $ilCtrl->getLinkTargetByClass("ilobjstudyprogrammegui", "view");
		}*/
		return '#';
	}


	/**
	 * Get childs of node
	 *
	 * @param                  $a_parent_node_id
	 *
	 * @global ilAccessHandler $ilAccess
	 * @internal param int $a_parent_id parent id
	 * @return array childs
	 */
	public function getChildsOfNode($a_parent_node_id) {
		global $ilAccess;

		$parent_obj = ilObjectFactoryWrapper::singleton()->getInstanceByRefId($a_parent_node_id);

		$children = array();

		// its currently only possible to have children on StudyProgrammes
		if($parent_obj instanceof ilObjStudyProgramme) {
			$children = $parent_obj->getChildren();

			// only return lp-children if there are no StudyProgramme-children
			if(!$parent_obj->hasChildren()) {
				$children = $parent_obj->getLPChildren();
			}
		}

		return $children;
	}


	/**
	 * Sort childs
	 *
	 * @param array $a_childs array of child nodes
	 * @param       $a_parent_node_id
	 *
	 * @internal param mixed $a_parent_node parent node
	 *
	 * @return array array of childs nodes
	 */
	public function sortChilds($a_childs, $a_parent_node_id) {
		//usort($a_childs, array( __CLASS__, "sortbyTitle" ));

		return $a_childs;
	}


	/**
	 * @param $a
	 * @param $b
	 *
	 * @return int
	 */
	public function sortbyTitle($a, $b) {
		return strcmp($a->getTitle(), $b->getTitle());
	}


	/**
	 * Is node clickable?
	 *
	 * @param mixed            $a_node node object/array
	 *
	 * @global ilAccessHandler $ilAccess
	 * @return boolean node clickable true/false
	 */
	public function isNodeClickable($a_node) {
		return true;
	}


	/**
	 * Get id of a node
	 *
	 * @param mixed $a_node node array or object
	 *
	 * @return string id of node
	 */
	public function getNodeId($a_node) {
		if(!is_null($a_node)) {
			return $a_node->getRefId();
		}
		return null;
	}

	/**
	 * List item start
	 *
	 * @param
	 * @return
	 */
	public function listItemStart($tpl, $a_node)
	{
		$tpl->setCurrentBlock("list_item_start");

		if ($this->getAjax() && $this->nodeHasVisibleChilds($a_node) || ($a_node instanceof ilStudyProgramme && $a_node->getParent() === null))
		{
			$tpl->touchBlock("li_closed");
		}
		$tpl->setVariable("DOM_NODE_ID",
			$this->getDomNodeIdForNodeId($this->getNodeId($a_node)));
		$tpl->parseCurrentBlock();
		$tpl->touchBlock("tag");
	}


	/**
	 * @return string
	 */
	public function getHTML() {
		$this->tpl->addJavascript($this->js_study_programme_path);
		$this->tpl->addCss($this->css_study_programme_path);

		$this->tpl->addOnLoadCode('$("#'.$this->getContainerId().'").study_programme_tree('.json_encode($this->js_conf).');');

		return parent::getHTML();
	}


	/**
	 * @param $node_id
	 */
	public function closeCertainNode($node_id) {
		if (in_array($node_id, $this->open_nodes))
		{
			$k = array_search($node_id, $this->open_nodes);
			unset($this->open_nodes[$k]);
		}
		$this->store->set("on_".$this->id, serialize($this->open_nodes));
	}

	/**
	 * @param $node_id
	 */
	public function openCertainNode($node_id) {
		$id = $this->getNodeIdForDomNodeId($node_id);
		if (!in_array($id, $this->open_nodes))
		{
			$this->open_nodes[] = $id;
		}
		$this->store->set("on_".$this->id, serialize($this->open_nodes));
	}


	/**
	 * Helper method to check access
	 *
	 * @param $a_which
	 * @param $a_ref_id
	 *
	 * @return bool
	 */
	protected function checkAccess($a_which, $a_ref_id) {
		return $this->access->checkAccess($a_which, '', $a_ref_id);
	}

	/**
	 *
	 * @param array $js_conf
	 */
	public function addJsConf($key, $value) {
		$this->js_conf[$key] = $value;
	}

	/**
	 *
	 * @return string
	 */
	public function getJsConf($key) {
		return $this->js_conf[$key];
	}

}

?>