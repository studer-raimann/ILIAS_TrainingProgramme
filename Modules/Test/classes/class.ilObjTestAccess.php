<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once "./Services/Object/classes/class.ilObjectAccess.php";
include_once "./Modules/Test/classes/inc.AssessmentConstants.php";
include_once './Services/AccessControl/interfaces/interface.ilConditionHandling.php';

/**
* Class ilObjTestAccess
*
* This class contains methods that check object specific conditions
* for accessing test objects.
*
* @author	Helmut Schottmueller <helmut.schottmueller@mac.com>
* @author 	Alex Killing <alex.killing@gmx.de>
* @version $Id: class.ilObjTestAccess.php 56020 2014-12-05 11:26:13Z bheyser $
*
* @ingroup ModulesTest
*/
class ilObjTestAccess extends ilObjectAccess implements ilConditionHandling
{
	/**
	* Checks wether a user may invoke a command or not
	* (this method is called by ilAccessHandler::checkAccess)
	*
	* Please do not check any preconditions handled by
	* ilConditionHandler here.
	*
	* @param	string		$a_cmd		command (not permission!)
	* @param	string		$a_permission	permission
	* @param	int			$a_ref_id	reference id
	* @param	int			$a_obj_id	object id
	* @param	int			$a_user_id	user id (if not provided, current user is taken)
	*
	* @return	boolean		true, if everything is ok
	*/
	function _checkAccess($a_cmd, $a_permission, $a_ref_id, $a_obj_id, $a_user_id = "")
	{
		global $ilUser, $lng, $rbacsystem, $ilAccess;
		
		if ($a_user_id == "")
		{
			$a_user_id = $ilUser->getId();
		}
		
		$is_admin = $rbacsystem->checkAccessOfUser($a_user_id,'write',$a_ref_id);		
		
		// check "global" online switch
		if(!self::_isOnline($a_obj_id) && !$is_admin)
		{
			$ilAccess->addInfoItem(IL_NO_OBJECT_ACCESS, $lng->txt("offline"));
			return false;
		}

		switch ($a_permission)
		{			
			case "visible":		
			case "read":		
				if (!ilObjTestAccess::_lookupCreationComplete($a_obj_id) &&
					!$is_admin)
				{
					$ilAccess->addInfoItem(IL_NO_OBJECT_ACCESS, $lng->txt("tst_warning_test_not_complete"));
					return false;
				}												
				break;
		}
		
		switch ($a_cmd)
		{
			case "eval_a":
			case "eval_stat":
				if (!ilObjTestAccess::_lookupCreationComplete($a_obj_id))
				{
					$ilAccess->addInfoItem(IL_NO_OBJECT_ACCESS, $lng->txt("tst_warning_test_not_complete"));
					return false;
				}
				break;

		}

		return true;
	}
	
	/**
	* Returns the maximum number of points available for a test pass
	*
	* @param boolean $random TRUE if the test is a random test, otherwise FALSE
	* @param int $test_id The test id
	* @param int $pass The test pass
	* @return int The available points for the test pass
	*/
	function _getMaxPointsForTestPass($random, $user_id, $test_id, $pass)
	{
		global $ilDB;
		$max = 0;
		if ($random)
		{
			$result = $ilDB->queryF("SELECT SUM(qpl_questions.points) maxpoints FROM tst_test_rnd_qst, qpl_questions, tst_active WHERE tst_active.active_id = tst_test_rnd_qst.active_fi AND tst_test_rnd_qst.question_fi = qpl_questions.question_id AND tst_active.test_fi = %s AND tst_test_rnd_qst.pass = %s AND tst_active.user_fi = %s",
				array('integer','integer','integer'),
				array($test_id, $pass, $user_id)
			);
			if ($result->numRows())
			{
				$row = $ilDB->fetchAssoc($result);
				$max = $row["maxpoints"];
			}
		}
		else
		{
			$result = $ilDB->queryF("SELECT SUM(qpl_questions.points) maxpoints FROM tst_test_question, qpl_questions WHERE tst_test_question.question_fi = qpl_questions.question_id AND tst_test_question.test_fi = %s",
				array('integer'),
				array($test_id)
			);
			if ($result->numRows())
			{
				$row = $ilDB->fetchAssoc($result);
				$max = $row["maxpoints"];
			}
		}
		return $max;
	}
	
	/**
	* Returns TRUE if the user with the user id $user_id passed the test with the object id $a_obj_id
	*
	* @param int $user_id The user id
	* @param int $a_obj_id The object id
	* @return boolean TRUE if the user passed the test, FALSE otherwise
	*/
	public static function _isPassed($user_id, $a_obj_id)
	{
		global $ilDB;
		$result = $ilDB->queryF("SELECT tst_result_cache.* FROM tst_result_cache, tst_active, tst_tests WHERE tst_active.test_fi = tst_tests.test_id AND tst_active.user_fi = %s AND tst_tests.obj_fi = %s AND tst_result_cache.active_fi = tst_active.active_id",
			array('integer','integer'),
			array($user_id, $a_obj_id)
		);
		if (!$result->numRows())
		{
			$result = $ilDB->queryF("SELECT tst_active.active_id FROM tst_active, tst_tests WHERE tst_active.test_fi = tst_tests.test_id AND tst_active.user_fi = %s AND tst_tests.obj_fi = %s",
				array('integer','integer'),
				array($user_id, $a_obj_id)
			);
			$row = $ilDB->fetchAssoc($result);
			if ($row['active_id'] > 0)
			{
				include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
				assQuestion::_updateTestResultCache($row['active_id']);
			}
			else
			{
				return false;
			}
		}
		$result = $ilDB->queryF("SELECT tst_result_cache.* FROM tst_result_cache, tst_active, tst_tests WHERE tst_active.test_fi = tst_tests.test_id AND tst_active.user_fi = %s AND tst_tests.obj_fi = %s AND tst_result_cache.active_fi = tst_active.active_id",
			array('integer','integer'),
			array($user_id, $a_obj_id)
		);
		if (!$result->numRows())
		{
			$result = $ilDB->queryF("SELECT tst_pass_result.*, tst_tests.pass_scoring, tst_tests.random_test, tst_tests.test_id FROM tst_pass_result, tst_active, tst_tests WHERE tst_active.test_fi = tst_tests.test_id AND tst_active.user_fi = %s AND tst_tests.obj_fi = %s AND tst_pass_result.active_fi = tst_active.active_id ORDER BY tst_pass_result.pass",
				array('integer','integer'),
				array($user_id, $a_obj_id)
			);
			$points = array();
			while ($row = $ilDB->fetchAssoc($result))
			{
				array_push($points, $row);
			}
			$reached = 0;
			$max = 0;
			if ($points[0]["pass_scoring"] == 0)
			{
				$reached = $points[count($points)-1]["points"];
				$max = $points[count($points)-1]["maxpoints"];
				if (!$max)
				{
					$active_id = $points[count($points)-1]["active_fi"];
					$pass = $points[count($points)-1]["pass"];
					if (strlen($active_id) && strlen($pass))
					{
						include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
						$res = assQuestion::_updateTestPassResults($active_id, $pass, null, $a_obj_id);
						$max = $res['maxpoints'];
						$reached = $res['points'];
					}
				}
			}
			else
			{
				foreach ($points as $row)
				{
					if ($row["points"] > $reached) 
					{
						$reached = $row["points"];
						$max = $row["maxpoints"];
						if (!$max)
						{
							$active_id = $row["active_fi"];
							$pass = $row["pass"];
							if (strlen($active_id) && strlen($pass))
							{
								include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
								$res = assQuestion::_updateTestPassResults($active_id, $pass, null, $a_obj_id);
								$max = $res['maxpoints'];
								$reached = $res['points'];
							}
						}
					}
				}
			}
			include_once "./Modules/Test/classes/class.assMarkSchema.php";
			$percentage = (!$max) ? 0 : ($reached / $max) * 100.0;
			$mark = ASS_MarkSchema::_getMatchingMarkFromObjId($a_obj_id, $percentage);
			return ($mark["passed"]) ? TRUE : FALSE;
		}
		else
		{
			$row = $ilDB->fetchAssoc($result);
			return ($row['passed']) ? true : false;
		}
	}
	
	/**
	 * Get possible conditions operators
	 */
	public static function getConditionOperators()
	{
		include_once './Services/AccessControl/classes/class.ilConditionHandler.php';
		return array(
			ilConditionHandler::OPERATOR_PASSED,
			ilConditionHandler::OPERATOR_FINISHED,
			ilConditionHandler::OPERATOR_NOT_FINISHED
		);
	}
	

	/**
	* check condition
	*
	* this method is called by ilConditionHandler
	*/
	public static function checkCondition($a_obj_id, $a_operator, $a_value, $a_usr_id)
	{
		include_once './Services/AccessControl/classes/class.ilConditionHandler.php';
		
		switch($a_operator)
		{
			case ilConditionHandler::OPERATOR_PASSED:
				return ilObjTestAccess::_isPassed($a_usr_id, $a_obj_id);
				break;

			case ilConditionHandler::OPERATOR_FINISHED:
				return ilObjTestAccess::_hasFinished($a_usr_id,$a_obj_id);

			case ilConditionHandler::OPERATOR_NOT_FINISHED:
				return !ilObjTestAccess::_hasFinished($a_usr_id,$a_obj_id);

			default:
				return true;
		}
		return true;
	}

	/**
	 * get commands
	 * 
	 * this method returns an array of all possible commands/permission combinations
	 * 
	 * example:	
	 * $commands = array
	 *	(
	 *		array("permission" => "read", "cmd" => "view", "lang_var" => "show"),
	 *		array("permission" => "write", "cmd" => "edit", "lang_var" => "edit"),
	 *	);
	 */
	function _getCommands()
	{
		$commands = array
		(
            array("permission" => "write", "cmd" => "questionsTabGateway", "lang_var" => "tst_edit_questions"),
			array("permission" => "write", "cmd" => "ilObjTestSettingsGeneralGUI::showForm", "lang_var" => "settings"),
			array("permission" => "read", "cmd" => "infoScreen", "lang_var" => "tst_run",
				"default" => true),
			//array("permission" => "write", "cmd" => "", "lang_var" => "edit"),
			array("permission" => "tst_statistics", "cmd" => "outEvaluation", "lang_var" => "tst_statistical_evaluation"),
		);
		
		return $commands;
	}

	//
	// object specific access related methods
	//

	/**
	* checks wether all necessary parts of the test are given
	*/
	function _lookupCreationComplete($a_obj_id)
	{
		global $ilDB;

		$result = $ilDB->queryF("SELECT complete FROM tst_tests WHERE obj_fi=%s",
			array('integer'),
			array($a_obj_id)
		);
		if ($result->numRows() == 1)
		{
			$row = $ilDB->fetchAssoc($result);
		}

		return ($row['complete']) ? true : false;
	}

/**
* Returns information if a specific user has finished a test
*
* @param integer $user_id Database id of the user
* @param integer test obj_id
* @return bool
* @access public
* @static
*/
	function _hasFinished($a_user_id,$a_obj_id)
	{
		global $ilDB;

		$res = $ilDB->queryF("SELECT active_id FROM tst_active WHERE user_fi = %s AND test_fi = %s AND tries > '0'",
			array('integer','integer'),
			array($a_user_id, ilObjTestAccess::_getTestIDFromObjectID($a_obj_id))
		);
		return $res->numRows() ? true : false;
	}

/**
* Returns the ILIAS test id for a given object id
* 
* @param integer $object_id The object id
* @return mixed The ILIAS test id or FALSE if the query was not successful
* @access public
*/
	function _getTestIDFromObjectID($object_id)
	{
		global $ilDB;
		$test_id = FALSE;
		$result = $ilDB->queryF("SELECT test_id FROM tst_tests WHERE obj_fi = %s",
			array('integer'),
			array($object_id)
		);
		if ($result->numRows())
		{
			$row = $ilDB->fetchAssoc($result);
			$test_id = $row["test_id"];
		}
		return $test_id;
	}
	
	function &_getTestQuestions($active_id, $pass = NULL)
	{
		if (is_null($pass))
		{
			$pass = 0;
		}
		$questions = array();
		
		global $ilDB;
		$result = $ilDB->queryF("SELECT test_fi FROM tst_active WHERE active_id = %s",
			array('integer'),
			array($active_id)
		);
		$test_id = "";
		if ($result->numRows())
		{
			$row = $ilDB->fetchAssoc($result);
			$test_id = $row["test_fi"];
		}
		else
		{
			return $questions;
		}
		$result = $ilDB->queryF("SELECT qpl_questions.question_id, qpl_questions.points FROM qpl_questions, tst_test_question WHERE tst_test_question.question_fi = qpl_questions.question_id AND tst_test_question.test_fi = %s ORDER BY tst_test_question.sequence",
			array('integer'),
			array($test_id)
		);
		if ($result->numRows())
		{
			// standard test
			while ($row = $ilDB->fetchAssoc($result))
			{
				array_push($questions, $row);
			}
		}
		else
		{
			// random test
			$result = $ilDB->queryF("SELECT qpl_questions.question_id, qpl_questions.points FROM qpl_questions, tst_test_rnd_qst WHERE tst_test_rnd_qst.question_fi = qpl_questions.question_id AND tst_test_rnd_qst.active_fi = %s AND tst_test_rnd_qst.pass = %s ORDER BY tst_test_rnd_qst.sequence",
				array('integer','integer'),
				array($active_id, $pass)
			);
			if ($result->numRows())
			{
				while ($row = $ilDB->fetchAssoc($result))
				{
					array_push($questions, $row);
				}
			}
		}
		return $questions;
	}
	
/**
* Returns true, if a test is complete for use
*
* @return boolean True, if the test is complete for use, otherwise false
* @access public
*/
	function _isComplete($a_obj_id)
	{
		global $ilDB;
		
		$query = "
			SELECT tst_tests.complete
			FROM object_data
			INNER JOIN tst_tests
			ON tst_tests.obj_fi = object_data.obj_id
			WHERE object_data.obj_id = %s
		";

		$res = $ilDB->queryF($query, array('integer'), array($a_obj_id));

		while( $row = $ilDB->fetchAssoc($res) )
		{
			return (bool)$row['complete'];
		}

		return false;
	}
	
	/**
	* Returns the database content of a test with a given id
	*
	* @param int $test_id Database id of the test
	* @return array An associative array with the contents of the tst_tests database row
	* @access public
	*/
	function &_getTestData($test_id)
	{
		global $ilDB;
		$result = $ilDB->queryF("SELECT * FROM tst_tests WHERE test_id = %s",
			array('integer'),
			array($test_id)
		);
		if (!$result->numRows())
		{
			return 0;
		}
		return $ilDB->fetchAssoc($result);
	}
	
	/**
	 * Lookup object id for test id
	 *
	 * @param		int		test id
	 * @return		int		object id
	 */
	function _lookupObjIdForTestId($a_test_id)
	{
		global $ilDB;

		$result = $ilDB->queryF("SELECT obj_fi FROM tst_tests WHERE test_id = %s",
			array('integer'),
			array($a_test_id)
		);

		$row = $ilDB->fetchAssoc($result);
		return $row["obj_fi"];
	}

	/**
	* Get all tests using a question pool for random selection
	*
	* @param    int     question pool id
	* @return 	array 	list if test obj ids
	* @access	public
	*/
	function _getRandomTestsForQuestionPool($qpl_id)
	{
		global $ilDB;
	
		$query = "
			SELECT DISTINCT t.obj_fi
			FROM tst_tests t
			INNER JOIN tst_rnd_quest_set_qpls r
			ON t.test_id = r.test_fi
			WHERE r.pool_fi = %s
		";
	
		$result = $ilDB->queryF($query, array('integer'), array($qpl_id));
	
		$tests = array();
		while ($row = $ilDB->fetchAssoc($result))
		{
		    $tests[] = $row['obj_fi'];
		}
	
		return $tests;
	}
	// fim.
	
/**
* Checks if a user is allowd to run an online exam
*
* @return mixed true if the user is allowed to run the online exam or if the test isn't an online exam, an alert message if the test is an online exam and the user is not allowed to run it
* @access public
*/
	function _lookupOnlineTestAccess($a_test_id, $a_user_id)
	{
		global $ilDB, $lng;
		
		$result = $ilDB->queryF("SELECT tst_tests.* FROM tst_tests WHERE tst_tests.obj_fi = %s",
			array('integer'),
			array($a_test_id)
		);
		if ($result->numRows())
		{
			$row = $ilDB->fetchAssoc($result);
			if ($row["fixed_participants"])
			{
				$result = $ilDB->queryF("SELECT * FROM tst_invited_user WHERE test_fi = %s AND user_fi = %s",
					array('integer','integer'),
					array($row["test_id"], $a_user_id)
				);
				if ($result->numRows())
				{
					$row = $ilDB->fetchAssoc($result);
					if (trim($row['clientip']) != "")
					{
						$row['clientip'] = preg_replace("/[^0-9.?*,:]+/","",$row['clientip']);
						$row['clientip'] = str_replace(".","\\.",$row['clientip']);
						$row['clientip'] = str_replace(Array("?","*",","), Array("[0-9]","[0-9]*","|"), $row['clientip']);
						if (!preg_match("/^".$row['clientip']."$/", $_SERVER["REMOTE_ADDR"])) 
						{
							return $lng->txt("tst_user_wrong_clientip");
						}
						else
						{
							return true;
						}	
					}
					else
					{
						return true;
					}
				}
				else
				{
					return $lng->txt("tst_user_not_invited");
				}
			}
			else
			{
				return true;
			}
		}
		else
		{
			return true;
		}
	}

	/**
	* Retrieves a participant name from active id
	*
	* @param integer $active_id Active ID of the participant
	* @return string The output name of the user
	* @access public
	*/
	function _getParticipantData($active_id)
	{
		global $lng, $ilDB;

		$result = $ilDB->queryF("SELECT * FROM tst_active WHERE active_id = %s",
			array("integer"),
			array($active_id)
		);
		$row = $ilDB->fetchAssoc($result);
		$user_id = $row["user_fi"];
		$test_id = $row["test_fi"];
		$importname = $row['importname'];

		$result = $ilDB->queryF("SELECT obj_fi FROM tst_tests WHERE test_id = %s",
			array("integer"),
			array($test_id)
		);
		$row = $ilDB->fetchAssoc($result);
		$obj_id = $row["obj_fi"];
		
		include_once "./Modules/Test/classes/class.ilObjTest.php";
		$is_anonymous = ilObjTest::_lookupAnonymity($obj_id);
		
		include_once './Services/User/classes/class.ilObjUser.php';
		$uname = ilObjUser::_lookupName($user_id);

		$name = "";
		if (strlen($importname))
		{
			$name = $importname . ' (' . $lng->txt('imported') . ')';
		}
		else if (strlen($uname["firstname"].$uname["lastname"]) == 0)
		{
			$name = $lng->txt("deleted_user");
		}
		else
		{
			if ($user_id == ANONYMOUS_USER_ID)
			{
				$name = $lastname;
			}
			else
			{
				$name = trim($uname["lastname"] . ", " . $uname["firstname"] . " " .  $uname["title"]);
			}
			if ($is_anonymous)
			{
				$name = $lng->txt("anonymous");
			}
		}
		return $name;
	}

	/**
	 * Get user id for active id
	 *
	 * @param	int		active ID of the participant
	 * @return	int		user id
	 */
	function _getParticipantId($active_id)
	{
		global $lng, $ilDB;

		$result = $ilDB->queryF("SELECT user_fi FROM tst_active WHERE active_id = %s",
			array("integer"),
			array($active_id)
		);
		$row = $ilDB->fetchAssoc($result);
		return $row["user_fi"];
	}


/**
* Returns an array containing the users who passed the test
*
* @return array An array containing the users who passed the test.
*         Format of the values of the resulting array:
*           array(
*             "user_id"        => user ID,
*             "max_points"     => maximum available points in the test
*             "reached_points" => maximum reached points of the user
*             "mark_short"     => short text of the passed mark
*             "mark_official"  => official text of the passed mark
*           )
* @access public
*/
	function &_getPassedUsers($a_obj_id)
	{
		global $ilDB;

		$passed_users = array();
		// Maybe SELECT DISTINCT(tst_active.user_fi)... ?
		$userresult = $ilDB->queryF("
			SELECT tst_active.active_id, COUNT(tst_sequence.active_fi) sequences
			FROM tst_tests
			INNER JOIN tst_active
			ON tst_active.test_fi = tst_tests.test_id
			LEFT JOIN tst_sequence
			ON tst_sequence.active_fi = tst_active.active_id
			WHERE tst_tests.obj_fi = %s
			GROUP BY tst_active.active_id
			",
			array('integer'), array($a_obj_id)
		);
		$all_participants = array();
		$notAttempted = array();
		while ($row = $ilDB->fetchAssoc($userresult))
		{
			if($row['sequences'] == 0)
			{
				$notAttempted[$row['active_id']] = $row['active_id'];
			}

			$all_participants[$row['active_id']] = $row['active_id'];
		}
		
		$result = $ilDB->query("SELECT tst_result_cache.*, tst_active.user_fi FROM tst_result_cache, tst_active WHERE tst_active.active_id = tst_result_cache.active_fi AND " . $ilDB->in('active_fi', $all_participants, false, 'integer'));
		$found_all = ($result->numRows() == count($all_participants)) ? true : false;
		if (!$found_all)
		{
			// if the result cache entries do not exist, create them
			$found_participants = array();
			while ($data = $ilDB->fetchAssoc($result))
			{
				array_push($found_participants, $data['active_fi']);
			}
			foreach ($all_participants as $active_id)
			{
				if (!in_array($active_id, $found_participants))
				{
					include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
					assQuestion::_updateTestResultCache($active_id);
				}
			}
			$result = $ilDB->query("SELECT tst_result_cache.*, tst_active.user_fi FROM tst_result_cache, tst_active WHERE tst_active.active_id = tst_result_cache.active_fi AND " . $ilDB->in('active_fi', $all_participants, false, 'integer'));
		}
		while ($data = $ilDB->fetchAssoc($result))
		{
			if( isset($notAttempted[$data['active_fi']]) )
			{
				$data['failed'] = 0;
				$data['passed'] = 0;
			}

			$data['user_id'] = $data['user_fi'];
			array_push($passed_users, $data);
		}
		return $passed_users;
	}

	/**
	* check whether goto script will succeed
	*/
	function _checkGoto($a_target)
	{
		global $ilAccess;
		
		$t_arr = explode("_", $a_target);

		if ($t_arr[0] != "tst" || ((int) $t_arr[1]) <= 0)
		{
			return false;
		}

		if ($ilAccess->checkAccess("read", "", $t_arr[1]))
		{
			return true;
		}
		return false;
	}

	/**
	 * returns the objects's OFFline status
	 *
	 * Used in ListGUI and Learning Progress
	 *
	 * @param int $a_obj_id
	 * @return bool
	 */
	static function _isOffline($a_obj_id)
	{
//		global $ilUser;
//		return (self::_lookupOnlineTestAccess($a_obj_id, $ilUser->getId()) !== true) ||
//			(!ilObjTestAccess::_lookupCreationComplete($a_obj_id));
		return !self::_isOnline($a_obj_id);
	}

	/**
	 * returns the objects's ONline status
	 *
	 * @param integer $a_obj_id
	 * @return boolean $online
	 */
	public static function _isOnline($a_obj_id)
	{
		global $ilDB;

		$query = "
			SELECT		test_id
			FROM		tst_tests
			WHERE		obj_fi = %s
			AND			online_status = 1
		";

		$result = $ilDB->queryF( $query, array('integer'), array($a_obj_id) );

		return $result->numRows() == 1;
	}
}

?>
