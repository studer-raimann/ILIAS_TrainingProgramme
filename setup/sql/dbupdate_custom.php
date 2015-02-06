<#1>
<?php

require_once("./Modules/TrainingProgramme/classes/model/class.ilTrainingProgramme.php");
require_once("./Modules/TrainingProgramme/classes/model/class.ilTrainingProgrammeAssignment.php");
require_once("./Modules/TrainingProgramme/classes/model/class.ilTrainingProgrammeProgress.php");

ilTrainingProgramme::installDB();
ilTrainingProgrammeAssignment::installDB();
ilTrainingProgrammeProgress::installDB();

?>

<#2>
<?php

require_once("./Modules/TrainingProgramme/classes/model/class.ilTrainingProgrammeProgress.php");
require_once("./Modules/TrainingProgramme/classes/model/class.ilTrainingProgrammeAssignment.php");

// Active Record does not support tuples as primary keys, so we have to
// set those on our own.
$ilDB->dropPrimaryKey(ilTrainingProgrammeProgress::returnDbTableName());
$ilDB->addPrimaryKey( ilTrainingProgrammeProgress::returnDbTableName()
					, array("assignment_id", "prg_id", "usr_id")
					);

?>