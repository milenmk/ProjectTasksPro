<?php

/**
 * This module add the ability to bulk delete project tasks and bulk clone task(s) from one project to another
 *
 *  @date           Last modified: 22.12.21 г., 14:32 ч.
 *
 *  @category       Dolibarr plugin
 *  @package        ProjectTasksPRO
 *  @link           https://rapidprogress.eu/
 *  @since          1.0
 *  @version        1.0
 *  @author         Ivan Valkov <sales@rapidprogress.eu>
 *  @license        GPL-2.0+
 *  @license        http://www.gnu.org/licenses/gpl-2.0.txt
 *  @copyright      Copyright (c) 2021 Rapid Progress Ltd.
 */

require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';

class ProjectTasksPro
{

	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var array Errors
	 */
	public $errors = array();


	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * Constructor
	 *
	 *  @param    DoliDB    $db    Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Show task lines with a particular parent
	 *
	 * @param   string      $inc				    Line number (start to 0, then increased by recursive call)
	 * @param   string      $parent				    Id of parent project to show (0 to show all)
	 * @param   Task[]      $lines                  Array of lines
	 * @param   int         $level				    Level (start to 0, then increased/decrease by recursive call), or -1 to show all level in order of $lines without the recursive groupment feature.
	 * @param   string      $var				    Color
	 * @param   int         $showproject		    Show project columns
	 * @param   int         $taskrole			    Array of roles of user for each tasks
	 * @param   int         $projectsListId		    List of id of project allowed to user (string separated with comma)
	 * @param   int         $addordertick		    Add a tick to move task
	 * @param   int         $projectidfortotallink  0 or Id of project to use on total line (link to see all time consumed for project)
	 * @param   string      $filterprogresscalc     filter text
	 * @param   string      $showbilltime           Add the column 'TimeToBill' and 'TimeBilled'
	 * @param   array       $arrayfields            Array with displayed coloumn information
	 * @return  int									Nb of tasks shown
	 */
	public function cptProjectLinesa(&$inc, $parent, $arrayofselected, &$lines, &$level, $var, $showproject, &$taskrole, $projectsListId = '', $addordertick = 0, $projectidfortotallink = 0, $filterprogresscalc = '', $showbilltime = 0, $arrayfields = array())
	{
		global $user, $langs, $conf, $db, $hookmanager;
		global $projectstatic, $taskstatic, $extrafields;

		$lastprojectid = 0;

		$projectsArrayId = explode(',', $projectsListId);
		if ($filterprogresscalc !== '') {
			foreach ($lines as $key => $line) {
				if (!empty($line->planned_workload) && !empty($line->duration)) {
					$filterprogresscalc = str_replace(' = ', ' == ', $filterprogresscalc);
					if (!eval($filterprogresscalc)) {
						unset($lines[$key]);
					}
				}
			}
			$lines = array_values($lines);
		}
		$numlines = count($lines);

		// We declare counter as global because we want to edit them into recursive call
		global $total_projectlinesa_spent, $total_projectlinesa_planned, $total_projectlinesa_spent_if_planned, $total_projectlinesa_declared_if_planned, $total_projectlinesa_tobill, $total_projectlinesa_billed;

		if ($level == 0) {
			$total_projectlinesa_spent = 0;
			$total_projectlinesa_planned = 0;
			$total_projectlinesa_spent_if_planned = 0;
			$total_projectlinesa_declared_if_planned = 0;
			$total_projectlinesa_tobill = 0;
			$total_projectlinesa_billed = 0;
		}

		for ($i = 0; $i < $numlines; $i++) {
			if ($parent == 0 && $level >= 0) {
				$level = 0; // if $level = -1, we dont' use sublevel recursion, we show all lines
			}

			// Process line
			// print "i:".$i."-".$lines[$i]->fk_project.'<br>';

			if ($lines[$i]->fk_parent == $parent || $level < 0) {       // if $level = -1, we dont' use sublevel recursion, we show all lines
				// Show task line.
				$showline = 1;
				$showlineingray = 0;

				// If there is filters to use
				if (is_array($taskrole)) {
					// If task not legitimate to show, search if a legitimate task exists later in tree
					if (!isset($taskrole[$lines[$i]->id]) && $lines[$i]->id != $lines[$i]->fk_parent) {
						// So search if task has a subtask legitimate to show
						$foundtaskforuserdeeper = 0;
						searchTaskInChild($foundtaskforuserdeeper, $lines[$i]->id, $lines, $taskrole);
						//print '$foundtaskforuserpeeper='.$foundtaskforuserdeeper.'<br>';
						if ($foundtaskforuserdeeper > 0) {
							$showlineingray = 1; // We will show line but in gray
						} else {
							$showline = 0; // No reason to show line
						}
					}
				} else {
					// Caller did not ask to filter on tasks of a specific user (this probably means he want also tasks of all users, into public project
					// or into all other projects if user has permission to).
					if (empty($user->rights->projet->all->lire)) {
						// User is not allowed on this project and project is not public, so we hide line
						if (!in_array($lines[$i]->fk_project, $projectsArrayId)) {
							// Note that having a user assigned to a task into a project user has no permission on, should not be possible
							// because assignement on task can be done only on contact of project.
							// If assignement was done and after, was removed from contact of project, then we can hide the line.
							$showline = 0;
						}
					}
				}

				if ($showline) {
					// Break on a new project
					if ($parent == 0 && $lines[$i]->fk_project != $lastprojectid) {
						$var = !$var;
						$lastprojectid = $lines[$i]->fk_project;
					}

					print '<tr class="oddeven" id="row-' . $lines[$i]->id . '">' . "\n";

					$projectstatic->id = $lines[$i]->fk_project;
					$projectstatic->ref = $lines[$i]->projectref;
					$projectstatic->public = $lines[$i]->public;
					$projectstatic->title = $lines[$i]->projectlabel;
					$projectstatic->usage_bill_time = $lines[$i]->usage_bill_time;
					$projectstatic->status = $lines[$i]->projectstatus;

					$taskstatic->id = $lines[$i]->id;
					$taskstatic->ref = $lines[$i]->ref;
					$taskstatic->label = ($taskrole[$lines[$i]->id] ? $langs->trans("YourRole") . ': ' . $taskrole[$lines[$i]->id] : '');
					$taskstatic->projectstatus = $lines[$i]->projectstatus;
					$taskstatic->progress = $lines[$i]->progress;
					$taskstatic->fk_statut = $lines[$i]->status;
					$taskstatic->date_start = $lines[$i]->date_start;
					$taskstatic->date_end = $lines[$i]->date_end;
					$taskstatic->datee = $lines[$i]->date_end; // deprecated
					$taskstatic->planned_workload = $lines[$i]->planned_workload;
					$taskstatic->duration_effective = $lines[$i]->duration;


					if ($showproject) {
						// Project ref
						print "<td>";
						//if ($showlineingray) print '<i>';
						if ($lines[$i]->public || in_array($lines[$i]->fk_project, $projectsArrayId) || !empty($user->rights->projet->all->lire)) {
							print $projectstatic->getNomUrl(1);
						} else {
							print $projectstatic->getNomUrl(1, 'nolink');
						}
						//if ($showlineingray) print '</i>';
						print "</td>";

						// Project status
						print '<td>';
						$projectstatic->statut = $lines[$i]->projectstatus;
						print $projectstatic->getLibStatut(2);
						print "</td>";
					}

					// Ref of task
					if (count($arrayfields) > 0 && !empty($arrayfields['t.ref']['checked'])) {
						print '<td class="nowraponall">';
						if ($showlineingray) {
							print '<i>' . img_object('', 'projecttask') . ' ' . $lines[$i]->ref . '</i>';
						} else {
							print $taskstatic->getNomUrl(1, 'withproject');
						}
						print '</td>';
					}

					// Title of task
					if (count($arrayfields) > 0 && !empty($arrayfields['t.label']['checked'])) {
						print '<td>';
						if ($showlineingray) {
							print '<i>';
						}
						//else print '<a href="'.DOL_URL_ROOT.'/projet/tasks/task.php?id='.$lines[$i]->id.'&withproject=1">';
						for ($k = 0; $k < $level; $k++) {
							print '<div class="marginleftonly">';
						}
						print $lines[$i]->label;
						for ($k = 0; $k < $level; $k++) {
							print '</div>';
						}
						if ($showlineingray) {
							print '</i>';
						}
						//else print '</a>';
						print "</td>\n";
					}

					if (count($arrayfields) > 0 && !empty($arrayfields['t.description']['checked'])) {
						print "<td>";
						print $lines[$i]->description;
						print "</td>\n";
					}

					// Date start
					if (count($arrayfields) > 0 && !empty($arrayfields['t.dateo']['checked'])) {
						print '<td class="center">';
						print dol_print_date($lines[$i]->date_start, 'dayhour');
						print '</td>';
					}

					// Date end
					if (count($arrayfields) > 0 && !empty($arrayfields['t.datee']['checked'])) {
						print '<td class="center">';
						print dol_print_date($lines[$i]->date_end, 'dayhour');
						if ($taskstatic->hasDelay()) {
							print img_warning($langs->trans("Late"));
						}
						print '</td>';
					}

					$plannedworkloadoutputformat = 'allhourmin';
					$timespentoutputformat = 'allhourmin';
					if (!empty($conf->global->PROJECT_PLANNED_WORKLOAD_FORMAT)) {
						$plannedworkloadoutputformat = $conf->global->PROJECT_PLANNED_WORKLOAD_FORMAT;
					}
					if (!empty($conf->global->PROJECT_TIMES_SPENT_FORMAT)) {
						$timespentoutputformat = $conf->global->PROJECT_TIME_SPENT_FORMAT;
					}

					// Planned Workload (in working hours)
					if (count($arrayfields) > 0 && !empty($arrayfields['t.planned_workload']['checked'])) {
						print '<td class="right">';
						$fullhour = convertSecondToTime($lines[$i]->planned_workload, $plannedworkloadoutputformat);
						$workingdelay = convertSecondToTime($lines[$i]->planned_workload, 'all', 86400, 7); // TODO Replace 86400 and 7 to take account working hours per day and working day per weeks
						if ($lines[$i]->planned_workload != '') {
							print $fullhour;
							// TODO Add delay taking account of working hours per day and working day per week
							//if ($workingdelay != $fullhour) print '<br>('.$workingdelay.')';
						}
						//else print '--:--';
						print '</td>';
					}

					// Time spent
					if (count($arrayfields) > 0 && !empty($arrayfields['t.duration_effective']['checked'])) {
						print '<td class="right">';
						if ($showlineingray) {
							print '<i>';
						} else {
							print '<a href="' . DOL_URL_ROOT . '/projet/tasks/time.php?id=' . $lines[$i]->id . ($showproject ? '' : '&withproject=1') . '">';
						}
						if ($lines[$i]->duration) {
							print convertSecondToTime($lines[$i]->duration, $timespentoutputformat);
						} else {
							print '--:--';
						}
						if ($showlineingray) {
							print '</i>';
						} else {
							print '</a>';
						}
						print '</td>';
					}

					// Progress calculated (Note: ->duration is time spent)
					if (count($arrayfields) > 0 && !empty($arrayfields['t.progress_calculated']['checked'])) {
						print '<td class="right">';
						if ($lines[$i]->planned_workload || $lines[$i]->duration) {
							if ($lines[$i]->planned_workload) {
								print round(100 * $lines[$i]->duration / $lines[$i]->planned_workload, 2) . ' %';
							} else {
								print '<span class="opacitymedium">' . $langs->trans('WorkloadNotDefined') . '</span>';
							}
						}
						print '</td>';
					}

					// Progress declared
					if (count($arrayfields) > 0 && !empty($arrayfields['t.progress']['checked'])) {
						print '<td class="right">';
						if ($lines[$i]->progress != '') {
							print getTaskProgressBadge($taskstatic);
						}
						print '</td>';
					}

					// resume
					if (count($arrayfields) > 0 && !empty($arrayfields['t.progress_summary']['checked'])) {
						print '<td class="right">';
						if ($lines[$i]->progress != '' && $lines[$i]->duration) {
							print getTaskProgressView($taskstatic, false, false);
						}
						print '</td>';
					}

					if ($showbilltime) {
						// Time not billed
						if (count($arrayfields) > 0 && !empty($arrayfields['t.tobill']['checked'])) {
							print '<td class="right">';
							if ($lines[$i]->usage_bill_time) {
								print convertSecondToTime($lines[$i]->tobill, 'allhourmin');
								$total_projectlinesa_tobill += $lines[$i]->tobill;
							} else {
								print '<span class="opacitymedium">' . $langs->trans("NA") . '</span>';
							}
							print '</td>';
						}

						// Time billed
						if (count($arrayfields) > 0 && !empty($arrayfields['t.billed']['checked'])) {
							print '<td class="right">';
							if ($lines[$i]->usage_bill_time) {
								print convertSecondToTime($lines[$i]->billed, 'allhourmin');
								$total_projectlinesa_billed += $lines[$i]->billed;
							} else {
								print '<span class="opacitymedium">' . $langs->trans("NA") . '</span>';
							}
							print '</td>';
						}
					}

					// Contacts of task
					if (count($arrayfields) > 0 && !empty($arrayfields['c.assigned']['checked'])) {
						print '<td class="center">';
						foreach (array('internal', 'external') as $source) {
							$tab = $lines[$i]->liste_contact(-1, $source);
							$num = count($tab);
							if (!empty($num)) {
								foreach ($tab as $contacttask) {
									//var_dump($contacttask);
									if ($source == 'internal') {
										$c = new User($db);
									} else {
										$c = new Contact($db);
									}
									$c->fetch($contacttask['id']);
									if (!empty($c->photo)) {
										print $c->getNomUrl(-2) . '&nbsp;';
									} else {
										if (get_class($c) == 'User') {
											print $c->getNomUrl(2, '', 0, 0, 24, 1); //.'&nbsp;';
										} else {
											print $c->getNomUrl(2); //.'&nbsp;';
										}
									}
								}
							}
						}
						print '</td>';
					}

					// Extra fields
					$extrafieldsobjectkey = $taskstatic->table_element;
					$obj = $lines[$i];
					include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_print_fields.tpl.php';
					// Fields from hook
					$parameters = array('arrayfields' => $arrayfields, 'obj' => $lines[$i]);
					$reshook = $hookmanager->executeHooks('printFieldListValue', $parameters); // Note that $action and $object may have been modified by hook
					print $hookmanager->resPrint;

					// Action column
					print '<td class="nowrap" align="center">';
					$selected = 0;
					if (in_array($obj->id, $arrayofselected)) {
						$selected = 1;
					}
					print '<input id="cb' . $obj->id . '" class="flat checkforselect" type="checkbox" name="toselect[]" value="' . $obj->id . '"' . ($selected ? ' checked="checked"' : '') . '>';

					print '</td>';

					print "</tr>\n";
					if (!$showlineingray) {
						$inc++;
					}

					if ($level >= 0) {    // Call sublevels
						$level++;
						if ($lines[$i]->id) {
							$this->cptProjectLinesa($inc, $lines[$i]->id, $arrayofselected, $lines, $level, $var, $showproject, $taskrole, $projectsListId = '', $addordertick = 0, $projectidfortotallink = 0, $filterprogresscalc = '', $showbilltime = 0, $arrayfields);
						}
						$level--;
					}

					$total_projectlinesa_spent += $lines[$i]->duration;
					$total_projectlinesa_planned += $lines[$i]->planned_workload;
					if ($lines[$i]->planned_workload) {
						$total_projectlinesa_spent_if_planned += $lines[$i]->duration;
					}
					if ($lines[$i]->planned_workload) {
						$total_projectlinesa_declared_if_planned += $lines[$i]->planned_workload * $lines[$i]->progress / 100;
					}
				}
			} else {
			}
		}

		if (($total_projectlinesa_planned > 0 || $total_projectlinesa_spent > 0 || $total_projectlinesa_tobill > 0 || $total_projectlinesa_billed > 0)
			&& $level <= 0
		) {
			print '<tr class="liste_total nodrag nodrop">';
			print '<td class="liste_total">' . $langs->trans("Total") . '</td>';
			if ($showproject) {
				print '<td></td><td></td>';
			}
			if (count($arrayfields) > 0 && !empty($arrayfields['t.label']['checked'])) {
				print '<td></td>';
			}
			if (count($arrayfields) > 0 && !empty($arrayfields['t.dateo']['checked'])) {
				print '<td></td>';
			}
			if (count($arrayfields) > 0 && !empty($arrayfields['t.datee']['checked'])) {
				print '<td></td>';
			}
			if (count($arrayfields) > 0 && !empty($arrayfields['t.planned_workload']['checked'])) {
				print '<td class="nowrap liste_total right">';
				print convertSecondToTime($total_projectlinesa_planned, 'allhourmin');
				print '</td>';
			}
			if (count($arrayfields) > 0 && !empty($arrayfields['t.duration_effective']['checked'])) {
				print '<td class="nowrap liste_total right">';
				if ($projectidfortotallink > 0) {
					print '<a href="' . DOL_URL_ROOT . '/projet/tasks/time.php?projectid=' . $projectidfortotallink . ($showproject ? '' : '&withproject=1') . '">';
				}
				print convertSecondToTime($total_projectlinesa_spent, 'allhourmin');
				if ($projectidfortotallink > 0) {
					print '</a>';
				}
				print '</td>';
			}

			if ($total_projectlinesa_planned) {
				$totalAverageDeclaredProgress = round(100 * $total_projectlinesa_declared_if_planned / $total_projectlinesa_planned, 2);
				$totalCalculatedProgress = round(100 * $total_projectlinesa_spent / $total_projectlinesa_planned, 2);

				// this conf is actually hidden, by default we use 10% for "be carefull or warning"
				$warningRatio = !empty($conf->global->PROJECT_TIME_SPEND_WARNING_PERCENT) ? (1 + $conf->global->PROJECT_TIME_SPEND_WARNING_PERCENT / 100) : 1.10;

				// define progress color according to time spend vs workload
				$progressBarClass = 'progress-bar-info';
				$badgeClass = 'badge ';

				if ($totalCalculatedProgress > $totalAverageDeclaredProgress) {
					$progressBarClass = 'progress-bar-danger';
					$badgeClass .= 'badge-danger';
				} elseif ($totalCalculatedProgress * $warningRatio >= $totalAverageDeclaredProgress) { // warning if close at 1%
					$progressBarClass = 'progress-bar-warning';
					$badgeClass .= 'badge-warning';
				} else {
					$progressBarClass = 'progress-bar-success';
					$badgeClass .= 'badge-success';
				}
			}

			if (count($arrayfields) > 0 && !empty($arrayfields['t.progress_calculated']['checked'])) {
				print '<td class="nowrap liste_total right">';
				if ($total_projectlinesa_planned) {
					print $totalCalculatedProgress . ' %';
				}
				print '</td>';
			}
			if (count($arrayfields) > 0 && !empty($arrayfields['t.progress']['checked'])) {
				print '<td class="nowrap liste_total right">';
				if ($total_projectlinesa_planned) {
					print '<span class="' . $badgeClass . '" >' . $totalAverageDeclaredProgress . ' %</span>';
				}
				print '</td>';
			}

			// resume
			if (count($arrayfields) > 0 && !empty($arrayfields['t.progress_summary']['checked'])) {
				print '<td class="right">';
				if ($total_projectlinesa_planned) {
					print '</span>';
					print '    <div class="progress sm" title="' . $totalAverageDeclaredProgress . '%" >';
					print '        <div class="progress-bar ' . $progressBarClass . '" style="width: ' . $totalAverageDeclaredProgress . '%"></div>';
					print '    </div>';
					print '</div>';
				}
				print '</td>';
			}

			if ($showbilltime) {
				if (count($arrayfields) > 0 && !empty($arrayfields['t.tobill']['checked'])) {
					print '<td class="nowrap liste_total right">';
					print convertSecondToTime($total_projectlinesa_tobill, 'allhourmin');
					print '</td>';
				}
				if (count($arrayfields) > 0 && !empty($arrayfields['t.billed']['checked'])) {
					print '<td class="nowrap liste_total right">';
					print convertSecondToTime($total_projectlinesa_billed, 'allhourmin');
					print '</td>';
				}
			}
			// Contacts of task for backward compatibility,
			if (!empty($conf->global->PROJECT_SHOW_CONTACTS_IN_LIST)) {
				print '<td></td>';
			}
			// Contacts of task
			if (count($arrayfields) > 0 && !empty($arrayfields['c.assigned']['checked'])) {
				print '<td></td>';
			}
			print '<td class=""></td>';
			print '<td class=""></td>';
			print '</tr>';
		}

		return $inc;
	}

	/**
	 * Load projects info from database
	 *
	 * @return void
	 */
	public function loadProjectInfo()
	{
		$sql = 'SELECT p.rowid, p.ref, p.title FROM ' . MAIN_DB_PREFIX . 'projet p';

		$resql = $this->db->query($sql);

		if ($resql) {
			$num_rows = $this->db->num_rows($resql);

			$out = '';
			$out .= '<select id="SelectedProjectId" name="SelectedProjectId">';
			if ($num_rows) {
				while ($res = $this->db->fetch_object($resql)) {
					$out .= '<option value="' . $res->rowid . '">' . $res->ref . ' - ' . $res->title . '</option>';
				}
			}
			$out .= '</select>';

			$this->db->free($resql);

			return $out;
		} else {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->db->lasterror();
			return -1;
		}
	}
}
