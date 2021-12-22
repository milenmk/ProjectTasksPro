<?php

/**
 * This module add the ability to bulk delete project tasks and bulk clone task(s) from one project to another
 *
 *  @date           Last modified: 22.12.21 г., 14:40 ч.
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

/* Copyright (C) 2005      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2019 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2017 Regis Houssin        <regis.houssin@inodbox.com>
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
 *  \file       htdocs/custom/projecttaskspro/projet_tab_clone_taskss.php
 *  \ingroup    project
 *  \brief      List all tasks of a project and clone them
 */

$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/main.inc.php';
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . '/main.inc.php')) {
    $res = @include substr($tmp, 0, ($i + 1)) . '/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php')) {
    $res = @include dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php';
}
// Try main.inc.php using relative path
if (!$res && file_exists('../main.inc.php')) {
    $res = @include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
    $res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
    $res = @include '../../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
if ($conf->categorie->enabled) {
    require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
}

require_once './class/projecttaskspro.class.php';

// Load translation files required by the page
$langsLoad = array('projects', 'users', 'companies');
if (!empty($conf->eventorganization->enabled)) {
    $langsLoad[] = 'eventorganization';
}

$langs->loadLangs($langsLoad);

$action = GETPOST('action', 'aZ09');
$show_files = GETPOST('show_files', 'int');
$confirm = GETPOST('confirm', 'alpha');

$det = GETPOST('det', 'alpha');
if (!empty($det)) {
    $preselectedtask = explode(',', $det);
    $toselect = $preselectedtask;
} else {
    $toselect = GETPOST('toselect', 'array');
}

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$taskref = GETPOST('taskref', 'alpha');

// Load variable for pagination
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
    $page = 0;
}     // If $page is not defined, or '' or -1 or if we click on clear filters
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

$backtopage = GETPOST('backtopage', 'alpha');
$cancel = GETPOST('cancel', 'alpha');

$search_user_id = GETPOST('search_user_id', 'int');
$search_taskref = GETPOST('search_taskref');
$search_tasklabel = GETPOST('search_tasklabel');
$search_taskdescription = GETPOST('search_taskdescription');
$search_dtstartday = GETPOST('search_dtstartday');
$search_dtstartmonth = GETPOST('search_dtstartmonth');
$search_dtstartyear = GETPOST('search_dtstartyear');
$search_dtendday = GETPOST('search_dtendday');
$search_dtendmonth = GETPOST('search_dtendmonth');
$search_dtendyear = GETPOST('search_dtendyear');
$search_planedworkload = GETPOST('search_planedworkload');
$search_timespend = GETPOST('search_timespend');
$search_progresscalc = GETPOST('search_progresscalc');
$search_progressdeclare = GETPOST('search_progressdeclare');

$selectedtasks = GETPOST('selectedtasks', 'array');
$SelectedProjectId = GETPOST('SelectedProjectId', 'int');

$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'projecttasklist';

//if (! $user->rights->projet->all->lire) $mine=1;	// Special for projects

$form = new Form($db);
$formother = new FormOther($db);
$object = new Project($db);
$taskstatic = new Task($db);
$extrafields = new ExtraFields($db);
$clonetasks = new ProjectTasksPro($db);

include DOL_DOCUMENT_ROOT . '/core/actions_fetchobject.inc.php'; // Must be include, not include_once
if (!empty($conf->global->PROJECT_ALLOW_COMMENT_ON_PROJECT) && method_exists($object, 'fetchComments') && empty($object->comments)) {
    $object->fetchComments();
}

if ($id > 0 || !empty($ref)) {
    // fetch optionals attributes and labels
    $extrafields->fetch_name_optionals_label($object->table_element);
}
$extrafields->fetch_name_optionals_label($taskstatic->table_element);
$search_array_options = $extrafields->getOptionalsFromPost($taskstatic->table_element, '', 'search_');


// Default sort order (if not yet defined by previous GETPOST)
if (!$sortfield) {
    reset($object->fields);
    $sortfield = "t." . key($object->fields);
}   // Set here default search field. By default 1st field in definition. Reset is required to avoid key() to return null.
if (!$sortorder) {
    $sortorder = "ASC";
}


// Security check
$socid = 0;
//if ($user->socid > 0) $socid = $user->socid;    // For external user, no check is done on company because readability is managed by public status of project and assignement.
$result = restrictedArea($user, 'projet', $id, 'projet&project');

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('projecttaskscard', 'globalcard'));

$progress = GETPOST('progress', 'int');
$label = GETPOST('label', 'alpha');
$description = GETPOST('description', 'restricthtml');
$planned_workloadhour = (GETPOST('planned_workloadhour', 'int') ? GETPOST('planned_workloadhour', 'int') : 0);
$planned_workloadmin = (GETPOST('planned_workloadmin', 'int') ? GETPOST('planned_workloadmin', 'int') : 0);
$planned_workload = $planned_workloadhour * 3600 + $planned_workloadmin * 60;

// Definition of fields for list
$arrayfields = array(
    't.ref' => array('label' => $langs->trans("RefTask"), 'checked' => 1, 'position' => 1),
    't.label' => array('label' => $langs->trans("LabelTask"), 'checked' => 1, 'position' => 2),
    't.description' => array('label' => $langs->trans("Description"), 'checked' => 0, 'position' => 3),
    't.dateo' => array('label' => $langs->trans("DateStart"), 'checked' => 1, 'position' => 4),
    't.datee' => array('label' => $langs->trans("Deadline"), 'checked' => 1, 'position' => 5),
    't.planned_workload' => array('label' => $langs->trans("PlannedWorkload"), 'checked' => 1, 'position' => 6),
    't.duration_effective' => array('label' => $langs->trans("TimeSpent"), 'checked' => 1, 'position' => 7),
    't.progress_calculated' => array('label' => $langs->trans("ProgressCalculated"), 'checked' => 1, 'position' => 8),
    't.progress' => array('label' => $langs->trans("ProgressDeclared"), 'checked' => 1, 'position' => 9),
    't.progress_summary' => array('label' => $langs->trans("TaskProgressSummary"), 'checked' => 1, 'position' => 10),
    'c.assigned' => array('label' => $langs->trans("TaskRessourceLinks"), 'checked' => 1, 'position' => 11),
);
if ($object->usage_bill_time) {
    $arrayfields['t.tobill'] = array('label' => $langs->trans("TimeToBill"), 'checked' => 0, 'position' => 11);
    $arrayfields['t.billed'] = array('label' => $langs->trans("TimeBilled"), 'checked' => 0, 'position' => 12);
}

// Extra fields
include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_array_fields.tpl.php';

$arrayfields = dol_sort_array($arrayfields, 'position');

$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;

/*
 * Actions
 */

$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
    // Selection of new fields
    include DOL_DOCUMENT_ROOT . '/core/actions_changeselectedfields.inc.php';

    // Purge search criteria
    if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
        $search_user_id = "";
        $search_taskref = '';
        $search_tasklabel = '';
        $search_dtstartday = '';
        $search_dtstartmonth = '';
        $search_dtstartyear = '';
        $search_dtendday = '';
        $search_dtendmonth = '';
        $search_dtendyear = '';
        $search_planedworkload = '';
        $search_timespend = '';
        $search_progresscalc = '';
        $search_progressdeclare = '';
        $toselect = '';
        $search_array_options = array();
    }
}

$morewherefilterarray = array();

if (!empty($search_taskref)) {
    $morewherefilterarray[] = natural_search('t.ref', $search_taskref, 0, 1);
}

if (!empty($search_tasklabel)) {
    $morewherefilterarray[] = natural_search('t.label', $search_tasklabel, 0, 1);
}

$moresql = dolSqlDateFilter('t.dateo', $search_dtstartday, $search_dtstartmonth, $search_dtstartyear, 1);
if ($moresql) {
    $morewherefilterarray[] = $moresql;
}

$moresql = dolSqlDateFilter('t.datee', $search_dtendday, $search_dtendmonth, $search_dtendyear, 1);
if ($moresql) {
    $morewherefilterarray[] = $moresql;
}

if (!empty($search_planedworkload)) {
    $morewherefilterarray[] = natural_search('t.planned_workload', $search_planedworkload, 1, 1);
}

if (!empty($search_timespend)) {
    $morewherefilterarray[] = natural_search('t.duration_effective', $search_timespend, 1, 1);
}

if (!empty($search_progresscalc)) {
    $filterprogresscalc = 'if ' . natural_search('round(100 * $line->duration / $line->planned_workload,2)', $search_progresscalc, 1, 1) . '{return 1;} else {return 0;}';
} else {
    $filterprogresscalc = '';
}

if (!empty($search_progressdeclare)) {
    $morewherefilterarray[] = natural_search('t.progress', $search_progressdeclare, 1, 1);
}

$morewherefilter = '';
if (count($morewherefilterarray) > 0) {
    $morewherefilter = ' AND ' . implode(' AND ', $morewherefilterarray);
}

/*
 * View
 */

$now = dol_now();
$socstatic = new Societe($db);
$projectstatic = new Project($db);
$taskstatic = new Task($db);
$userstatic = new User($db);

$title = $langs->trans("Project") . ' - ' . $langs->trans("Tasks") . ' - ' . $object->ref . ' ' . $object->name;
if (!empty($conf->global->MAIN_HTML_TITLE) && preg_match('/projectnameonly/', $conf->global->MAIN_HTML_TITLE) && $object->name) {
    $title = $object->ref . ' ' . $object->name . ' - ' . $langs->trans("Tasks");
}
$help_url = "EN:Module_Projects|FR:Module_Projets|ES:M&oacute;dulo_Proyectos";

llxHeader("", $title, $help_url);

$arrayofselected = is_array($toselect) ? $toselect : array();

if ($id > 0 || !empty($ref)) {
    $result = $object->fetch($id, $ref);
    if ($result < 0) {
        setEventMessages(null, $object->errors, 'errors');
    }
    $result = $object->fetch_thirdparty();
    if ($result < 0) {
        setEventMessages(null, $object->errors, 'errors');
    }
    $result = $object->fetch_optionals();
    if ($result < 0) {
        setEventMessages(null, $object->errors, 'errors');
    }

    if ($action == 'clone') {
        $formquestion = array(
            array('type' => 'other', 'name' => 'SelectedProjectId', 'label' => $langs->trans("SetProject"), 'value' => $clonetasks->loadProjectInfo())
        );
        print $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id . '&det=' . $det . '', $langs->trans("ToClone"), '', 'confirm_clone', $formquestion, '', 1, 300, 590);
    }
    if ($action == 'confirm_clone' && $confirm == 'yes') {

        $project = new Project($db);

        foreach ($toselect as $id) {

            $origin_task = new Task($db);
            $clone_task = new Task($db);

            $origin_task->fetch($id, $ref = '', $loadparentdata = 0);

            $defaultref = '';
            $obj = empty($conf->global->PROJECT_TASK_ADDON) ? 'mod_task_simple' : $conf->global->PROJECT_TASK_ADDON;
            if (!empty($conf->global->PROJECT_TASK_ADDON) && is_readable(DOL_DOCUMENT_ROOT . "/core/modules/project/task/" . $conf->global->PROJECT_TASK_ADDON . ".php")) {
                require_once DOL_DOCUMENT_ROOT . "/core/modules/project/task/" . $conf->global->PROJECT_TASK_ADDON . '.php';
                $modTask = new $obj;
                $defaultref = $modTask->getNextValue(0, $clone_task);
            }

            if (!$error) {
                $clone_task->fk_project = GETPOST('SelectedProjectId', 'int');
                $clone_task->ref = $defaultref;
                $clone_task->label = $origin_task->label;
                $clone_task->description = $origin_task->description;
                $clone_task->planned_workload = $origin_task->planned_workload;
                $clone_task->fk_task_parent = $origin_task->fk_task_parent;
                $clone_task->date_c = dol_now();
                $clone_task->date_start = $origin_task->date_start;
                $clone_task->date_end = $origin_task->date_end;
                $clone_task->progress = $origin_task->progress;

                // Fill array 'array_options' with data from add form
                $ret = $extrafields->setOptionalsFromPost(null, $clone_task);

                $taskid = $clone_task->create($user);

                if ($taskid > 0) {
                    $result = $clone_task->add_contact(GETPOST("userid", 'int'), 'TASKEXECUTIVE', 'internal');
                    print '<meta http-equiv="refresh" content="0;url=' . DOL_URL_ROOT . '/projet/tasks.php?id=' . GETPOST('SelectedProjectId', 'int') . '">';
                } else {
                    if ($db->lasterrno() == 'DB_ERROR_RECORD_ALREADY_EXISTS') {
                        $langs->load("projects");
                        setEventMessages($langs->trans('NewTaskRefSuggested'), '', 'warnings');
                        $duplicate_code_error = true;
                    } else {
                        setEventMessages($clone_task->error, $clone_task->errors, 'errors');
                    }
                    $action = 'create';
                    $error++;
                }
            }
        }
    }

    // To verify role of users
    $userWrite = $object->restrictedProjectArea($user, 'write');

    $tab = (GETPOSTISSET('tab') ? GETPOST('tab') : 'cloneprojecttasks');

    $head = project_prepare_head($object);
    print dol_get_fiche_head($head, $tab, $langs->trans("Project"), -1, ($object->public ? 'projectpub' : 'project'));

    $param = '&id=' . $object->id;
    if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
        $param .= '&contextpage=' . urlencode($contextpage);
    }
    if ($search_user_id) {
        $param .= '&search_user_id=' . urlencode($search_user_id);
    }
    if ($search_taskref) {
        $param .= '&search_taskref=' . urlencode($search_taskref);
    }
    if ($search_tasklabel) {
        $param .= '&search_tasklabel=' . urlencode($search_tasklabel);
    }
    if ($search_taskdescription) {
        $param .= '&search_taskdescription=' . urlencode($search_taskdescription);
    }
    if ($search_dtstartday) {
        $param .= '&search_dtstartday=' . urlencode($search_dtstartday);
    }
    if ($search_dtstartmonth) {
        $param .= '&search_dtstartmonth=' . urlencode($search_dtstartmonth);
    }
    if ($search_dtstartyear) {
        $param .= '&search_dtstartyear=' . urlencode($search_dtstartyear);
    }
    if ($search_dtendday) {
        $param .= '&search_dtendday=' . urlencode($search_dtendday);
    }
    if ($search_dtendmonth) {
        $param .= '&search_dtendmonth=' . urlencode($search_dtendmonth);
    }
    if ($search_dtendyear) {
        $param .= '&search_dtendyear=' . urlencode($search_dtendyear);
    }
    if ($search_planedworkload) {
        $param .= '&search_planedworkload=' . urlencode($search_planedworkload);
    }
    if ($search_timespend) {
        $param .= '&search_timespend=' . urlencode($search_timespend);
    }
    if ($search_progresscalc) {
        $param .= '&search_progresscalc=' . urlencode($search_progresscalc);
    }
    if ($search_progressdeclare) {
        $param .= '&search_progressdeclare=' . urlencode($search_progressdeclare);
    }
    if ($optioncss != '') {
        $param .= '&optioncss=' . urlencode($optioncss);
    }
    // Add $param from extra fields
    include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_search_param.tpl.php';

    // Project card

    $linkback = '<a href="' . DOL_URL_ROOT . '/projet/list.php?restore_lastsearch_values=1">' . $langs->trans("BackToList") . '</a>';

    $morehtmlref = '<div class="refidno">';
    // Title
    $morehtmlref .= $object->title;
    // Thirdparty
    if ($object->thirdparty->id > 0) {
        $morehtmlref .= '<br>' . $langs->trans('ThirdParty') . ' : ' . $object->thirdparty->getNomUrl(1, 'project');
    }
    $morehtmlref .= '</div>';

    // Define a complementary filter for search of next/prev ref.
    if (!$user->rights->projet->all->lire) {
        $objectsListId = $object->getProjectsAuthorizedForUser($user, 0, 0);
        $object->next_prev_filter = " rowid IN (" . $db->sanitize(count($objectsListId) ? join(',', array_keys($objectsListId)) : '0') . ")";
    }

    dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

    print '<div class="clearboth"></div>';

    print dol_get_fiche_end();
}

if ($id > 0 || !empty($ref)) {
    $selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage); // This also change content of $arrayfields

    /*
     * Projet card in view mode
     */

    print '<br>';

    print '<form method="post" id="searchFormList">';
    if ($optioncss != '') {
        print '<input type="hidden" name="optioncss" value="' . $optioncss . '">';
    }
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="list">';
    print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
    print '<input type="hidden" name="sortfield" value="' . $sortfield . '">';
    print '<input type="hidden" name="sortorder" value="' . $sortorder . '">';
    print '<input type="hidden" name="page" value="' . $page . '">';
    print '<input type="hidden" name="contextpage" value="' . $contextpage . '">';

    $title = $langs->trans("ListOfTasks");

    //print load_fiche_titre($title, $linktotasks . ' &nbsp; ' . $linktocreatetask, 'projecttask');
    print_barre_liste($form->textwithpicto($title, $texthelp), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $num, $nbtotalofrecords, 'projecttask', 0, $newcardbutton, '', $limit, 0, 0, 1);

    // Get list of tasks in tasksarray and taskarrayfiltered
    // We need all tasks (even not limited to a user because a task to user can have a parent that is not affected to him).
    $filteronthirdpartyid = $socid;
    $tasksarray = $taskstatic->getTasksArray(0, 0, $object->id, $filteronthirdpartyid, 0, '', -1, $morewherefilter, 0, 0, $extrafields, 1, $search_array_options);

    // We load also tasks limited to a particular user
    $tmpuser = new User($db);
    if ($search_user_id > 0) {
        $tmpuser->fetch($search_user_id);
    }

    $tasksrole = ($tmpuser->id > 0 ? $taskstatic->getUserRolesForProjectsOrTasks(0, $tmpuser, $object->id, 0) : '');
    //var_dump($tasksarray);
    //var_dump($tasksrole);

    if (!empty($conf->use_javascript_ajax)) {
        include DOL_DOCUMENT_ROOT . '/core/tpl/ajaxrow.tpl.php';
    }

    // Filter on categories
    $moreforfilter = '';
    if (count($tasksarray) > 0) {
        $moreforfilter .= '<div class="divsearchfield">';
        $moreforfilter .= $langs->trans("TasksAssignedTo") . ': ';
        $moreforfilter .= $form->select_dolusers($tmpuser->id > 0 ? $tmpuser->id : '', 'search_user_id', 1);
        $moreforfilter .= '</div>';
    }
    if ($moreforfilter) {
        print '<div class="liste_titre liste_titre_bydiv centpercent">';
        print $moreforfilter;
        print '</div>';
    }

    $selectedfields .= $form->showCheckAddButtons('checkforselect', 1);

    print '<div class="div-table-responsive">';
    print '<table id="tablelines" class="tagtable nobottom liste' . ($moreforfilter ? " listwithfilterbefore" : "") . '">';

    // Fields title search
    print '<tr class="liste_titre_filter">';

    if (!empty($arrayfields['t.ref']['checked'])) {
        print '<td class="liste_titre">';
        print '<input class="flat searchstring maxwidth50" type="text" name="search_taskref" value="' . dol_escape_htmltag($search_taskref) . '">';
        print '</td>';
    }

    if (!empty($arrayfields['t.label']['checked'])) {
        print '<td class="liste_titre">';
        print '<input class="flat searchstring maxwidth100" type="text" name="search_tasklabel" value="' . dol_escape_htmltag($search_tasklabel) . '">';
        print '</td>';
    }

    if (!empty($arrayfields['t.description']['checked'])) {
        print '<td class="liste_titre">';
        print '<input class="flat searchstring maxwidth100" type="text" name="search_taskdescription" value="' . dol_escape_htmltag($search_taskdescription) . '">';
        print '</td>';
    }

    if (!empty($arrayfields['t.dateo']['checked'])) {
        print '<td class="liste_titre center">';
        print '<span class="nowraponall"><input class="flat valignmiddle width20" type="text" maxlength="2" name="search_dtstartday" value="' . $search_dtstartday . '">';
        print '<input class="flat valignmiddle width20" type="text" maxlength="2" name="search_dtstartmonth" value="' . $search_dtstartmonth . '"></span>';
        $formother->select_year($search_dtstartyear ? $search_dtstartyear : -1, 'search_dtstartyear', 1, 20, 5);
        print '</td>';
    }

    if (!empty($arrayfields['t.datee']['checked'])) {
        print '<td class="liste_titre center">';
        print '<span class="nowraponall"><input class="flat valignmiddle width20" type="text" maxlength="2" name="search_dtendday" value="' . $search_dtendday . '">';
        print '<input class="flat valignmiddle width20" type="text" maxlength="2" name="search_dtendmonth" value="' . $search_dtendmonth . '"></span>';
        $formother->select_year($search_dtendyear ? $search_dtendyear : -1, 'search_dtendyear', 1, 20, 5);
        print '</td>';
    }

    if (!empty($arrayfields['t.planned_workload']['checked'])) {
        print '<td class="liste_titre right">';
        print '<input class="flat" type="text" size="4" name="search_planedworkload" value="' . $search_planedworkload . '">';
        print '</td>';
    }

    if (!empty($arrayfields['t.duration_effective']['checked'])) {
        print '<td class="liste_titre right">';
        print '<input class="flat" type="text" size="4" name="search_timespend" value="' . $search_timespend . '">';
        print '</td>';
    }

    if (!empty($arrayfields['t.progress_calculated']['checked'])) {
        print '<td class="liste_titre right">';
        print '<input class="flat" type="text" size="4" name="search_progresscalc" value="' . $search_progresscalc . '">';
        print '</td>';
    }

    if (!empty($arrayfields['t.progress']['checked'])) {
        print '<td class="liste_titre right">';
        print '<input class="flat" type="text" size="4" name="search_progressdeclare" value="' . $search_progressdeclare . '">';
        print '</td>';
    }

    // progress resume not searchable
    print '<td class="liste_titre right"></td>';

    if ($object->usage_bill_time) {
        if (!empty($arrayfields['t.tobill']['checked'])) {
            print '<td class="liste_titre right">';
            print '</td>';
        }

        if (!empty($arrayfields['t.billed']['checked'])) {
            print '<td class="liste_titre right">';
            print '</td>';
        }
    }

    if (!empty($arrayfields['c.assigned']['checked'])) {
        print '<td class="liste_titre right">';
        print '</td>';
    }

    $extrafieldsobjectkey = $taskstatic->table_element;
    include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_search_input.tpl.php';

    // Action column
    print '<td class="liste_titre maxwidthsearch">';
    $searchpicto = $form->showFilterButtons();
    print $searchpicto;
    print '</td>';
    print "</tr>\n";

    print '<tr class="liste_titre nodrag nodrop">';
    if (!empty($arrayfields['t.ref']['checked'])) {
        print_liste_field_titre($arrayfields['t.ref']['label'], $_SERVER["PHP_SELF"], '', '', $param, '', $sortfield, $sortorder, '');
    }
    if (!empty($arrayfields['t.label']['checked'])) {
        print_liste_field_titre($arrayfields['t.label']['label'], $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, '');
    }
    if (!empty($arrayfields['t.description']['checked'])) {
        print_liste_field_titre($arrayfields['t.description']['label'], $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, '');
    }
    if (!empty($arrayfields['t.dateo']['checked'])) {
        print_liste_field_titre($arrayfields['t.dateo']['label'], $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, 'center ');
    }
    if (!empty($arrayfields['t.datee']['checked'])) {
        print_liste_field_titre($arrayfields['t.datee']['label'], $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, 'center ');
    }
    if (!empty($arrayfields['t.planned_workload']['checked'])) {
        print_liste_field_titre($arrayfields['t.planned_workload']['label'], $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, 'right ', '', 1);
    }
    if (!empty($arrayfields['t.duration_effective']['checked'])) {
        print_liste_field_titre($arrayfields['t.duration_effective']['label'], $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, 'right ', '', 1);
    }
    if (!empty($arrayfields['t.progress_calculated']['checked'])) {
        print_liste_field_titre($arrayfields['t.progress_calculated']['label'], $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, 'right ', '', 1);
    }
    if (!empty($arrayfields['t.progress']['checked'])) {
        print_liste_field_titre($arrayfields['t.progress']['label'], $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, 'right ', '', 1);
    }
    if (!empty($arrayfields['t.progress_summary']['checked'])) {
        print_liste_field_titre($arrayfields['t.progress_summary']['label'], $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, 'center ', '', 1);
    }
    if ($object->usage_bill_time) {
        if (!empty($arrayfields['t.tobill']['checked'])) {
            print_liste_field_titre($arrayfields['t.tobill']['label'], $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, 'right ');
        }
        if (!empty($arrayfields['t.billed']['checked'])) {
            print_liste_field_titre($arrayfields['t.billed']['label'], $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, 'right ');
        }
    }
    if (!empty($arrayfields['c.assigned']['checked'])) {
        print_liste_field_titre($arrayfields['c.assigned']['label'], $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, 'center ', '');
    }
    // Extra fields
    $disablesortlink = 1;
    include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_search_title.tpl.php';
    // Hook fields
    $parameters = array('arrayfields' => $arrayfields, 'param' => $param, 'sortfield' => $sortfield, 'sortorder' => $sortorder);
    $reshook = $hookmanager->executeHooks('printFieldListTitle', $parameters); // Note that $action and $object may have been modified by hook
    print $hookmanager->resPrint;
    print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], "", '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
    print "</tr>\n";

    if (count($tasksarray) > 0) {
        // Show all lines in taskarray (recursive function to go down on tree)
        $j = 0;
        $level = 0;
        $nboftaskshown = $clonetasks->cptProjectLinesa($j, 0, $arrayofselected, $tasksarray, $level, true, 0, $tasksrole, $object->id, 1, $object->id, $filterprogresscalc, ($object->usage_bill_time ? 1 : 0), $arrayfields);
    } else {
        $colspan = 10;
        if ($object->usage_bill_time) {
            $colspan += 2;
        }
        print '<tr class="oddeven nobottom"><td colspan="' . $colspan . '"><span class="opacitymedium">' . $langs->trans("NoTasks") . '</span></td></tr>';
    }

    print "</table>";
    print '</div>';

    print '</form>';

    // Clone
    if ($user->rights->projet->creer) {
        if ($userWrite > 0) {

            print '<a id="po" class="butAction' . ($conf->use_javascript_ajax ? ' reposition' : '') . '" >' . $langs->trans("ToClone") . '</a>';

            print '<script type="text/javascript">
                   $(document).ready(function() {
                   $("#po").click(function() {
                   darr = [];
                   $("input:checkbox[class=\'flat checkforselect\']:checked").each(function(){
                   darr.push($(this).val());
                   });
                   if(darr.length == 0)
                   {
                      alert("' . $langs->trans('AlertSetTask') . '");
                   }
                   else
                   {
                      var pourl = "' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=clone&det="+darr;

                      window.location.href = pourl;
                  }

                  });

                  });
                   ';

            print '</script>';
        }
    }

    // Test if database is clean. If not we clean it.
    //print 'mode='.$_REQUEST["mode"].' $nboftaskshown='.$nboftaskshown.' count($tasksarray)='.count($tasksarray).' count($tasksrole)='.count($tasksrole).'<br>';
    if (!empty($user->rights->projet->all->lire)) {    // We make test to clean only if user has permission to see all (test may report false positive otherwise)
        if ($search_user_id == $user->id) {
            if ($nboftaskshown < count($tasksrole)) {
                include_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
                cleanCorruptedTree($db, 'projet_task', 'fk_task_parent');
            }
        } else {
            if ($nboftaskshown < count($tasksarray) && !GETPOST('search_user_id', 'int')) {
                include_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
                cleanCorruptedTree($db, 'projet_task', 'fk_task_parent');
            }
        }
    }
}


// End of page
llxFooter();
$db->close();
