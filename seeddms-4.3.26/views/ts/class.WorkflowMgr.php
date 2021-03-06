<?php
/**
 * Implementation of WorkspaceMgr view
 *
 * @category   DMS
 * @package    SeedDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Include parent class
 */
require_once("class.Bootstrap.php");

/**
 * Class which outputs the html page for WorkspaceMgr view
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_View_WorkflowMgr extends SeedDMS_Bootstrap_Style {

	function js() { /* {{{ */
		header('Content-Type: application/javascript; charset=UTF-8');
?>

function checkForm(num)
{
	msg = new Array();
	eval("var formObj = document.form" + num + ";");

	if (formObj.name.value == "") msg.push("<?php printMLText("js_no_name");?>");
	if (msg != "")
	{
  	noty({
  		text: msg.join('<br />'),
  		type: 'error',
      dismissQueue: true,
  		layout: 'topRight',
  		theme: 'defaultTheme',
			_timeout: 1500,
  	});
		return false;
	}
	else
		return true;
}

$(document).ready(function() {
	$('body').on('submit', '#form1', function(ev){
		if(checkForm()) return;
		ev.preventDefault();
	});
	$( "#selector" ).change(function() {
		$('div.ajax').trigger('update', {workflowid: $(this).val()});
	});
});
<?php
	} /* }}} */

	function info() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$selworkflow = $this->params['selworkflow'];
		if($selworkflow) { ?>
<div id="workflowgraph">
<iframe src="out.WorkflowGraph.php?workflow=<?php echo $selworkflow->getID(); ?>" width="100%" height="550" style="border: 1px solid #AAA;"></iframe>
</div>
<?php }
	} /* }}} */

	function showWorkflowForm($workflow) { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$workflows = $this->params['allworkflows'];
		$workflowstates = $this->params['allworkflowstates'];

		if($workflow) {
			$transitions = $workflow->getTransitions();
			$initstate = $workflow->getInitState();
			$hasinitstate = true;
			$missesug = false;
			if($transitions) {
				$hasinitstate = false;
				foreach($transitions as $transition) {
					$transusers = $transition->getUsers();
					$transgroups = $transition->getGroups();
					if(!$transusers && !$transgroups) {
						$missesug = true;
					}
					if($transition->getState()->getID() == $initstate->getID())
						$hasinitstate = true;
				}
			}
			if($missesug)
				$this->errorMsg('One of the transitions has neither a user nor a group!');
			if(!$hasinitstate)
				$this->errorMsg('None of the transitions starts with the initial state of the workflow!');

				if($workflow->isUsed()) {
?>
				<p><?php echo getMLText('workflow_in_use') ?></p>
<?php
				} else {
?>
			  <a class="standardText btn" href="../out/out.RemoveWorkflow.php?workflowid=<?php print $workflow->getID();?>"><i class="icon-remove"></i> <?php printMLText("rm_workflow");?></a>
<?php
				}
		}
?>
	<form action="../op/op.WorkflowMgr.php" method="post" enctype="multipart/form-data">
<?php
	if($workflow) {
		echo createHiddenFieldWithKey('editworkflow');
?>
	<input type="Hidden" name="workflowid" value="<?php print $workflow->getID();?>">
	<input type="Hidden" name="action" value="editworkflow">
<?php
	} else {
		echo createHiddenFieldWithKey('addworkflow');
?>
		<input type="hidden" name="action" value="addworkflow">
<?php
	}
?>
	<table class="table-condensed">
		<tr>
			<td><?php printMLText("workflow_name");?>:</td>
			<td><input type="text" name="name" value="<?php print ($workflow ? htmlspecialchars($workflow->getName()) : "");?>"></td>
		</tr>
		<tr>
			<td><?php printMLText("workflow_initstate");?>:</td>
			<td><select name="initstate">
<?php
			foreach($workflowstates as $workflowstate) {
				echo "<option value=\"".$workflowstate->getID()."\"";
				if($workflow && $workflow->getInitState()->getID() == $workflowstate->getID())
					echo " selected=\"selected\"";
				echo ">".htmlspecialchars($workflowstate->getName())."</option>\n";
			}
?>
			</select></td>
		</tr>

		<tr>
			<td></td>
			<td><button type="submit" class="btn"><i class="icon-save"></i> <?php printMLText("save")?></button></td>
		</tr>
	</table>
	</form>
<?php
		if($workflow) {
		$actions = $dms->getAllWorkflowActions();
		if($actions) {
		$transitions = $workflow->getTransitions();
		echo "<table class=\"table table-condensed\"><thead>";
		echo "<tr><th>".getMLText('state_and_next_state')."</th><th>".getMLText('action')."</th><th>".getMLText('users_and_groups')."</th><th></th></tr></thead><tbody>";
		if($transitions) {
			foreach($transitions as $transition) {
				$state = $transition->getState();
				$nextstate = $transition->getNextState();
				$action = $transition->getAction();
				$transusers = $transition->getUsers();
				$transgroups = $transition->getGroups();
				echo "<tr";
				if(!$transusers && !$transgroups) {
					echo " class=\"error\"";
				}
				echo "><td>".$state->getName()."<br />";
				echo $nextstate->getName();
				$docstatus = $nextstate->getDocumentStatus();
				if($docstatus == S_RELEASED || $docstatus == S_REJECTED) {
					echo "<br /><i class=\"icon-arrow-right\"></i> ".getOverallStatusText($docstatus);
				}
				echo "</td>";
				echo "<td>".$action->getName()."</td>";
				echo "<td>";
				foreach($transusers as $transuser) {
					$u = $transuser->getUser();
					echo getMLText('user').": ".$u->getFullName();
					echo "<br />";
				}
				foreach($transgroups as $transgroup) {
					$g = $transgroup->getGroup();
					echo getMLText('at_least_n_users_of_group',
						array("number_of_users" => $transgroup->getNumOfUsers(),
							"group" => $g->getName()));
					echo "<br />";
				}
				echo "</td>";
				echo "<td>";
?>
<form class="form-inline" action="../op/op.RemoveTransitionFromWorkflow.php" method="post">
  <?php echo createHiddenFieldWithKey('removetransitionfromworkflow'); ?>
	<input type="hidden" name="workflow" value="<?php print $workflow->getID();?>">
	<input type="hidden" name="transition" value="<?php print $transition->getID(); ?>">
	<button type="submit" class="btn"><i class="icon-remove"></i> <?php printMLText("delete");?></button>
</form>
<?php
				echo "</td>";
				echo "</tr>\n";
			}
		}
		echo "</tbody></table>";
?>
<form class="form-inline" action="../op/op.AddTransitionToWorkflow.php" method="post">
<?php
		echo "<table class=\"table table-condensed\"><thead></thead><tbody>";
			echo "<tr>";
			echo "<td>";
			echo "<select name=\"state\">";
			$states = $dms->getAllWorkflowStates();
			foreach($states as $state) {
				echo "<option value=\"".$state->getID()."\">".$state->getName()."</option>";
			}
			echo "</select><br />";
			echo "<select name=\"nextstate\">";
			$states = $dms->getAllWorkflowStates();
			foreach($states as $state) {
				echo "<option value=\"".$state->getID()."\">".$state->getName()."</option>";
			}
			echo "</select>";
			echo "</td>";
			echo "<td>";
			echo "<select name=\"action\">";
			foreach($actions as $action) {
				echo "<option value=\"".$action->getID()."\">".$action->getName()."</option>";
			}
			echo "</select>";
			echo "</td>";
			echo "<td>";
      echo "<select class=\"chzn-select\" name=\"users[]\" multiple=\"multiple\" data-placeholder=\"".getMLText('select_users')."\" data-no_results_text=\"".getMLText('unknown_user')."\">";
			$allusers = $dms->getAllUsers();
			foreach($allusers as $usr) {
				print "<option value=\"".$usr->getID()."\">". htmlspecialchars($usr->getLogin()." - ".$usr->getFullName())."</option>";
			}
			echo "</select>";
			echo "<br />";
      echo "<select class=\"chzn-select\" name=\"groups[]\" multiple=\"multiple\" data-placeholder=\"".getMLText('select_groups')."\" data-no_results_text=\"".getMLText('unknown_group')."\">";
			$allgroups = $dms->getAllGroups();
			foreach($allgroups as $grp) {
				print "<option value=\"".$grp->getID()."\">". htmlspecialchars($grp->getName())."</option>";
			}
			echo "</select>";
			echo "</td>";
			echo "<td>";
?>
  <?php echo createHiddenFieldWithKey('addtransitiontoworkflow'); ?>
	<input type="hidden" name="workflow" value="<?php print $workflow->getID();?>">
	<input type="submit" class="btn" value="<?php printMLText("add");?>">
<?php
			echo "</td>";
			echo "</tr>\n";
		echo "</tbody></table>";
?>
</form>
<?php
		}
		}
	} /* }}} */

	function form() { /* {{{ */
		$selworkflow = $this->params['selworkflow'];

		$this->showWorkflowForm($selworkflow);
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$selworkflow = $this->params['selworkflow'];
		$workflows = $this->params['allworkflows'];
		$workflowstates = $this->params['allworkflowstates'];

		$this->htmlStartPage(getMLText("admin_tools"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");
		$this->contentHeading(getMLText("workflow_management"));
?>

<div class="row-fluid">
<div class="span4">
<div class="well">
<?php echo getMLText("selection")?>:
<select id="selector" class="span9">
<option value="-1"><?php echo getMLText("choose_workflow")?>
<option value="0"><?php echo getMLText("add_workflow")?>
<?php
		foreach ($workflows as $currWorkflow) {
			print "<option value=\"".$currWorkflow->getID()."\" ".($selworkflow && $currWorkflow->getID()==$selworkflow->getID() ? 'selected' : '').">" . htmlspecialchars($currWorkflow->getName());
		}
?>
</select>
</div>
<div class="ajax" data-view="WorkflowMgr" data-action="info" <?php echo ($selworkflow ? "data-query=\"workflowid=".$selworkflow->getID()."\"" : "") ?>></div>
</div>

<div class="span8">
	<div class="well">
		<div class="ajax" data-view="WorkflowMgr" data-action="form" <?php echo ($selworkflow ? "data-query=\"workflowid=".$selworkflow->getID()."\"" : "") ?>></div>
	</div>
</div>
</div>

<?php
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
