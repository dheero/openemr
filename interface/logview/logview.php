<?php
/**
 * Log Viewer.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2017-2019 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */


require_once("../globals.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Crypto\CryptoGen;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Logging\EventAuditLogger;
use OpenEMR\Core\Header;

if (!AclMain::aclCheckCore('admin', 'users')) {
    die(xlt("Not Authorized"));
}

if (!empty($_GET)) {
    if (!CsrfUtils::verifyCsrfToken($_GET["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
}

?>
<html>
<head>
    <title><?php echo xlt('Logs Viewer'); ?></title>

    <?php Header::setupHeader(['datetime-picker']); ?>

    <style>
        .sortby {
            cursor: pointer;
        }
    </style>

    <script>
        //function to disable the event type field if the event name is disclosure
        function eventTypeChange(eventname)
        {
            if (eventname == "disclosure") {
                document.theform.type_event.disabled = true;
            }
            else {
                document.theform.type_event.disabled = false;
            }
        }

        // VicarePlus :: This invokes the find-patient popup.
        function sel_patient() {
            dlgopen('../main/calendar/find_patient_popup.php?pflag=0', '_blank', 500, 400);
        }

        // VicarePlus :: This is for callback by the find-patient popup.
        function setpatient(pid, lname, fname, dob) {
            var f = document.theform;
            f.form_patient.value = lname + ', ' + fname;
            f.form_pid.value = pid;
        }
    </script>
</head>
<body class="body_top">
<div id="container_div" class="container">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-header clearfix">
                <h2><?php echo xlt('Logs Viewer'); ?></h2>
            </div>
        </div>
    </div><!--end of header div-->
    <div class="container-fluid mb-3">
        <ul class="nav nav-pills">
            <li class="nav-item" id='li-main-log'>
                <a href='#' class="active nav-link font-weight-bold" id='main-log-li'><?php echo xlt('Main Log'); ?></a>
            </li>
            <li class="nav-item" id='li-others-log'>
                <a href='#' id='others-log-li' class="nav-link font-weight-bold"><?php echo xlt('Other Logs'); ?></a>
            </li>
        </ul>
    </div><!--end of nav-pills div-->
    <div class="row" id="main-log-div">
        <div class="col-sm-12">
            <div class="jumbotron jumbotron-fluid py-3">
                <div class="col-sm-12 col-md-12 col-lg-12">
                    <?php
                    $err_message=0;

                    $start_date = (!empty($_GET["start_date"])) ? DateTimeToYYYYMMDDHHMMSS($_GET["start_date"]) : date("Y-m-d") . " 00:00";
                    $end_date = (!empty($_GET["end_date"])) ? DateTimeToYYYYMMDDHHMMSS($_GET["end_date"]) : date("Y-m-d") . " 23:59";
                    /*
                    * Start date should not be greater than end date - Date Validation
                    */
                    if ($start_date > $end_date) {
                        echo "<table><tr class='alert'><td colspan='7'>";
                        echo xlt('Start Date should not be greater than End Date');
                        echo "</td></tr></table>";
                        $err_message=1;
                    }

                    if ($_GET["form_patient"]) {
                        $form_patient = isset($_GET["form_patient"]) ? $_GET["form_patient"] : "";
                    }

                    ?>
                    <?php
                    $form_user = isset($_REQUEST['form_user']) ? $_REQUEST['form_user'] : '';
                    $form_pid = isset($_REQUEST['form_pid']) ? $_REQUEST['form_pid'] : '';

                    if ($form_patient == '') {
                        $form_pid = '';
                    }

                    $res = sqlStatement("select distinct LEFT(date,10) as date from log order by date desc limit 30");
                    for ($iter=0; $row=sqlFetchArray($res); $iter++) {
                        $ret[$iter] = $row;
                    }

                    // Get the users list.
                    $sqlQuery = "SELECT username, fname, lname FROM users " .
                    "WHERE active = 1 AND ( info IS NULL OR info NOT LIKE '%Inactive%' ) ";

                    $ures = sqlStatement($sqlQuery);

                    $sortby = isset($_GET['sortby']) ? $_GET['sortby'] : '';
                    $direction = isset($_GET['direction']) ? $_GET['direction'] : '';
                    ?>

                    <div class="jumbotron jumbotron-fluid px-3 py-3">
                        <h3 class="text-center"><?php echo xlt('Main Log'); ?></h3>
                        <form method="get" name="theform" id="theform">
                            <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
                            <input type="hidden" name="direction" id="direction" value="<?php echo !empty($direction) ? attr($direction) : 'asc'; ?>" />
                            <input type="hidden" name="sortby" id="sortby" value="<?php echo attr($sortby); ?>" />
                            <input type=hidden name="csum" value="" />
                            <input type=hidden name="show" value="show" />
                            <div class="form-row">
                                <label class="col-sm-1 col-form-label" for="start_date"><?php echo xlt('Start Date'); ?>:</label>
                                <div class="col-sm-3">
                                    <input class="datetimepicker form-control" type="text" size="18" name="start_date" id="start_date" value="<?php echo attr(oeFormatDateTime($start_date, 0)); ?>" title="<?php echo xla('Start Date'); ?>" />
                                </div>
                                <label class="col-sm-1 col-form-label" for="end_date"><?php echo xlt('End Date'); ?>:</label>
                                <div class="col-sm-3">
                                    <input class="datetimepicker form-control" type="text" size="18" name="end_date" id="end_date" value="<?php echo attr(oeFormatDateTime($end_date, 0)); ?>" title="<?php echo xla('End Date'); ?>" />
                                </div>
                                <label class="col-sm-1 col-form-label" for="end_date"><?php echo xlt('Patient'); ?>:</label>
                                <div class="col-sm-3">
                                    <input type='text' size='20' class='form-control' name='form_patient' id='form_patient' style='cursor:pointer;' value='<?php echo $form_patient ? attr($form_patient) : xla('Click To Select'); ?>' onclick='sel_patient()' title='<?php echo xla('Click to select patient'); ?>' />
                                    <input type='hidden' name='form_pid' value='<?php echo attr($form_pid); ?>' />
                                </div>
                            </div>
                            <div class="form-row">
                                <label class="col-sm-1 col-form-label" for="form_user"><?php echo xlt('User'); ?>:</label>
                                <div class="col-sm-3">
                                    <select name='form_user' id='form_user' class='form-control'>
                                    <?php
                                    echo " <option value=''>" . xlt('All') . "</option>\n";
                                    while ($urow = sqlFetchArray($ures)) {
                                        if (!trim($urow['username'])) {
                                            continue;
                                        }

                                        echo " <option value='" . attr($urow['username']) . "'";
                                        if ($urow['username'] == $form_user) {
                                            echo " selected";
                                        }

                                        echo ">" . text($urow['lname']);
                                        if ($urow['fname']) {
                                            echo ", " . text($urow['fname']);
                                        }

                                        echo "</option>\n";
                                    }
                                    ?>
                                    </select>
                                </div>
                                <?php
                                $eventname = isset($_GET['eventname']) ? $_GET['eventname'] : '';
                                $res = sqlStatement("select distinct event from log order by event ASC");
                                $ename_list=array();
                                $j=0;
                                while ($erow = sqlFetchArray($res)) {
                                    if (!trim($erow['event'])) {
                                        continue;
                                    }

                                    $data = explode('-', $erow['event']);
                                    $data_c = count($data);
                                    $ename=$data[0];
                                    for ($i=1; $i<($data_c-1); $i++) {
                                        $ename.="-".$data[$i];
                                    }

                                    $ename_list[$j]=$ename;
                                    $j=$j+1;
                                }
                                $res1 = sqlStatement("select distinct event from extended_log order by event ASC");
                                // $j=0; // This can't be right!  -- Rod 2013-08-23
                                while ($row = sqlFetchArray($res1)) {
                                    if (!trim($row['event'])) {
                                        continue;
                                    }

                                        $new_event = explode('-', $row['event']);
                                        $no = count($new_event);
                                        $events=$new_event[0];
                                    for ($i=1; $i<($no-1); $i++) {
                                        $events.="-".$new_event[$i];
                                    }

                                    if ($events=="disclosure") {
                                        $ename_list[$j]=$events;
                                    }

                                        $j=$j+1;
                                }
                                $ename_list=array_unique($ename_list);
                                $ename_list=array_merge($ename_list);
                                $ecount=count($ename_list);
                                ?>
                                <label class="col-sm-1 col-form-label" for="form_user"><?php echo xlt('Name of Events'); ?>:</label>
                                <div class="col-sm-3">
                                    <select name='eventname' id='eventname' class='form-control' onchange='eventTypeChange(this.options[this.selectedIndex].value);'>
                                    <?php
                                    echo " <option value=''>" . xlt('All') . "</option>\n";
                                    for ($k=0; $k<$ecount; $k++) {
                                        echo " <option value='" . attr($ename_list[$k]) . "'";
                                        if ($ename_list[$k] == $eventname && $ename_list[$k]!= "") {
                                            echo " selected";
                                        }

                                        echo ">" . text($ename_list[$k]);
                                        echo "</option>\n";
                                    }
                                    ?>
                                    </select>
                                </div>
                                <label class="col-sm-1 col-form-label" for="type_event"><?php echo xlt('Type of Events'); ?>:</label>
                                <div class="col-sm-3">
                                    <?php
                                    $type_event = isset($_GET['type_event']) ? $_GET['type_event'] : '';
                                    $event_types = array("select", "update", "insert", "delete", "replace");
                                    $lcount = count($event_types);
                                    if ($eventname == "disclosure") {
                                        echo "<select name='type_event' id='type_event' class='form-control' disabled='disabled'>\n";
                                        echo " <option value=''>" . xlt('All') . "</option>\n";
                                        echo "</option>\n";
                                    } else {
                                        echo "<select name='type_event' id='type_event' class='form-control'>\n";
                                    }

                                    echo " <option value=''>" . xlt('All') . "</option>\n";
                                    for ($k=0; $k<$lcount; $k++) {
                                        echo " <option value='" .attr($event_types[$k]). "'";
                                        if ($event_types[$k] == $type_event && $event_types[$k]!= "") {
                                            echo " selected";
                                        }

                                        echo ">" . text(preg_replace('/^select$/', 'Query', $event_types[$k])); // Convert select to Query for MU2 requirement
                                        echo "</option>\n";
                                    }
                                    ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <?php $check_sum = isset($_GET['check_sum']) ? $_GET['check_sum'] : ''; ?>
                                <input type="checkbox" name="check_sum" id="check_sum" <?php echo ($check_sum == 'on') ? "checked" : ""; ?> />
                                <label for="check_sum"><?php echo xlt('Include Checksum'); ?></label>
                                <input type="hidden" name="event" value="<?php echo attr($event); ?>" />
                            </div>
                            <div class="btn-group" role="group">
                                <a href="javascript:document.theform.submit();" class="btn btn-secondary btn-save"><?php echo xlt('Submit'); ?></a>
                                <button type="button" id="valid_button" class="btn btn-secondary btn-transmit" onclick="validatelog();" data-loading-text="<i class='fa fa-circle-o-notch fa-spin'></i> <?php echo xla("Processing..."); ?>"><?php echo xlt('Validate'); ?></button>
                            </div>
                        </form>

                    <?php if (!(!empty($_GET['show']) && ($_GET['show'] = 'show') && $start_date && $end_date && ($err_message != 1))) { ?>
                        <?php if (empty($_GET['show']) || ($_GET['show'] != 'show')) { ?>
                            <div class="alert alert-info">
                                <?php echo xlt("Click the Submit button to display the main log"); ?>
                            </div>
                        <?php } ?>
                    <?php } else { ?>
                    <div class="table-responsive">
                    <table class="table table-striped">
                    <tr>
                    <th id="sortby_date" class="sortby" title="<?php echo xla('Sort by date/time'); ?>"><?php echo xlt('Date'); ?></th>
                    <th id="sortby_event" class="sortby" title="<?php echo xla('Sort by Event'); ?>"><?php echo xlt('Event'); ?></th>
                    <th id="sortby_category" class="sortby" title="<?php echo xla('Sort by Category'); ?>"><?php echo xlt('Category'); ?></th>
                    <th id="sortby_user" class="sortby" title="<?php echo xla('Sort by User'); ?>"><?php echo xlt('User'); ?></th>
                    <th id="sortby_cuser" class="sortby" title="<?php echo xla('Sort by Crt User'); ?>"><?php echo xlt('Certificate User'); ?></th>
                    <th id="sortby_group" class="sortby" title="<?php echo xla('Sort by Group'); ?>"><?php echo xlt('Group'); ?></th>
                    <th id="sortby_pid" class="sortby" title="<?php echo xla('Sort by PatientID'); ?>"><?php echo xlt('Patient ID'); ?></th>
                    <th id="sortby_success" class="sortby" title="<?php echo xla('Sort by Success'); ?>"><?php echo xlt('Success'); ?></th>
                    <th id="sortby_comments" class="sortby" title="<?php echo xla('Sort by Comments'); ?>"><?php echo xlt('Comments'); ?></th>
                        <?php  if ($check_sum) {?>
                    <th id="sortby_checksum" class="sortby" title="<?php echo xla('Sort by Checksum'); ?>"><?php echo xlt('Checksum'); ?></th>
                        <?php } ?>
                    </tr>
                        <?php
                        ?>
                    <input type="hidden" name="event" value="<?php echo attr($eventname) . "-" . attr($type_event) ?>" />
                        <?php

                        $tevent="";
                        $gev="";
                        if ($eventname != "" && $type_event != "") {
                            $getevent=$eventname."-".$type_event;
                        }

                        if (($eventname == "") && ($type_event != "")) {
                            $tevent=$type_event;
                        } else if ($type_event =="" && $eventname != "") {
                            $gev=$eventname;
                        } else if ($eventname == "") {
                            $gev = "";
                        } else {
                            $gev = $getevent;
                        }

                        if ($ret = EventAuditLogger::instance()->getEvents(array('sdate' => $start_date,'edate' => $end_date, 'user' => $form_user, 'patient' => $form_pid, 'sortby' => $_GET['sortby'], 'levent' =>$gev, 'tevent' =>$tevent,'direction' => $_GET['direction']))) {
                            // Set up crypto object (object will increase performance since caches used keys)
                            $cryptoGen = new CryptoGen();

                            foreach ($ret as $iter) {
                                //translate comments
                                $patterns = array ('/^success/','/^failure/','/ encounter/');
                                $replace = array ( xl('success'), xl('failure'), xl('encounter', '', ' '));

                                $log_id = $iter['id'];
                                $commentEncrStatus = "No";
                                $encryptVersion = 0;
                                $logEncryptData = EventAuditLogger::instance()->logCommentEncryptData($log_id);
                                if (count($logEncryptData) > 0) {
                                    $commentEncrStatus = $logEncryptData['encrypt'];
                                    $encryptVersion = $logEncryptData['version'];
                                }

                                //July 1, 2014: Ensoftek: Decrypt comment data if encrypted
                                if ($commentEncrStatus == "Yes") {
                                    if ($encryptVersion == 3) {
                                        // Use new openssl method
                                        if (extension_loaded('openssl')) {
                                            $trans_comments = $cryptoGen->decryptStandard($iter["comments"]);
                                            if ($trans_comments !== false) {
                                                $trans_comments = preg_replace($patterns, $replace, $trans_comments);
                                            } else {
                                                $trans_comments = xl("Unable to decrypt these comments since decryption failed.");
                                            }
                                        } else {
                                            $trans_comments = xl("Unable to decrypt these comments since the PHP openssl module is not installed.");
                                        }
                                    } else if ($encryptVersion == 2) {
                                        // Use new openssl method
                                        if (extension_loaded('openssl')) {
                                            $trans_comments = $cryptoGen->aes256DecryptTwo($iter["comments"]);
                                            if ($trans_comments !== false) {
                                                $trans_comments = preg_replace($patterns, $replace, $trans_comments);
                                            } else {
                                                $trans_comments = xl("Unable to decrypt these comments since decryption failed.");
                                            }
                                        } else {
                                            $trans_comments = xl("Unable to decrypt these comments since the PHP openssl module is not installed.");
                                        }
                                    } else if ($encryptVersion == 1) {
                                        // Use new openssl method
                                        if (extension_loaded('openssl')) {
                                            $trans_comments = preg_replace($patterns, $replace, $cryptoGen->aes256DecryptOne($iter["comments"]));
                                        } else {
                                            $trans_comments = xl("Unable to decrypt these comments since the PHP openssl module is not installed.");
                                        }
                                    } else { //$encryptVersion == 0
                                        // Use old mcrypt method
                                        if (extension_loaded('mcrypt')) {
                                            $trans_comments = preg_replace($patterns, $replace, $cryptoGen->aes256Decrypt_mycrypt($iter["comments"]));
                                        } else {
                                            $trans_comments = xl("Unable to decrypt these comments since the PHP mycrypt module is not installed.");
                                        }
                                    }
                                } else {
                                    $trans_comments = preg_replace($patterns, $replace, $iter["comments"]);
                                }
                                ?>
                        <tr>
                            <td><?php echo text(oeFormatDateTime($iter["date"])); ?></td>
                        <td><?php echo text(preg_replace('/select$/', 'Query', $iter["event"])); //Convert select term to Query for MU2 requirements ?></td>
                        <td><?php echo text($iter["category"]); ?></td>
                        <td><?php echo text($iter["user"]); ?></td>
                        <td><?php echo text($iter["crt_user"]); ?></td>
                        <td><?php echo text($iter["groupname"]); ?></td>
                        <td><?php echo text($iter["patient_id"]); ?></td>
                        <td><?php echo text($iter["success"]); ?></td>
                        <td><?php echo nl2br(text(preg_replace('/^select/i', 'Query', $trans_comments))); //Convert select term to Query for MU2 requirements ?></td>
                                <?php  if ($check_sum) { ?>
                    <td><?php echo text($iter["checksum"]); ?></td>
                        <?php } ?>
                        </tr>

                                <?php
                            }
                        }

                        if (($eventname=="disclosure") || ($gev == "")) {
                            $eventname="disclosure";
                            if ($ret = EventAuditLogger::instance()->getEvents(array('sdate' => $start_date,'edate' => $end_date, 'user' => $form_user, 'patient' => $form_pid, 'sortby' => $_GET['sortby'], 'event' =>$eventname))) {
                                foreach ($ret as $iter) {
                                    $comments=xl('Recipient Name').":".$iter["recipient"].";".xl('Disclosure Info').":".$iter["description"];
                                    ?>
                                <tr>
                                <td><?php echo text(oeFormatDateTime($iter["date"])); ?></td>
                            <td><?php echo xlt($iter["event"]); ?></td>
                            <td><?php echo xlt($iter["category"]); ?></td>
                            <td><?php echo text($iter["user"]); ?></td>
                            <td><?php echo text($iter["crt_user"]); ?></td>
                            <td><?php echo text($iter["groupname"]); ?></td>
                            <td><?php echo text($iter["patient_id"]); ?></td>
                            <td><?php echo text($iter["success"]); ?></td>
                            <td><?php echo text($comments); ?></td>
                                    <?php  if ($check_sum) { ?>
                                    <td><?php echo text($iter["checksum"]); ?></td>
                                <?php } ?>
                        </tr>
                                    <?php
                                }
                            }
                        }
                        ?>
                    </table>
                    </div>

                    <?php } ?>
                </div>
            </div>
        </div>
    </div><!--end of main log div-->
    </div>
    <div class="row oe-display" id="other-logs-div">
        <div class="col-sm-12">
            <div class="jumbotron jumbotron-fluid py-3">
                <div class="col-sm-12 col-md-12 col-lg-12">
                    <div class="col-lg">
                        <h3 class="text-center"><?php echo xlt('Other Logs'); ?></h3>
                        <div class="btn-group">
                            <a href='#' id='view-billing-log-link' class='btn btn-secondary' title='<?php echo xla('See messages from the last set of generated claims'); ?>'><?php echo xlt('Billing Log'); ?></a>
                            <a href='#' id='view-couchdb-log-link' class='btn btn-secondary' title='<?php echo xla('See couchdb error log'); ?>'><?php echo xlt('CouchDB Error Log'); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div><!--end of others log div-->

<script>

// jQuery stuff to make the page a little easier to use
$(function () {
    $("#other-logs-div").hide();
    $("#main-log-li").click(function(){
        $("#main-log-div").show(250);
        $("#other-logs-div").hide(250);
        $("#main-log-li").addClass("active");
        $("#others-log-li").removeClass("active");
    });

    $("#others-log-li").click(function(){
        $("#other-logs-div").show(250);
        $("#main-log-div").hide(250);
        $("#others-log-li").addClass("active");
        $("#main-log-li").removeClass("active");
    });

    // billing log modal
    $("#view-billing-log-link").click( function() {
        top.restoreSession();
        dlgopen('../billing/customize_log.php', '_blank', 500, 400);
    });
    // couchdb log modal
    $("#view-couchdb-log-link").click( function() {
        top.restoreSession();
        dlgopen('../couchdb/couchdb_log.php', '_blank', 500, 400);
    });

    // click-able column headers to sort the list
    $('.sortby')
    $("#sortby_date").click(function() { set_sort_direction(); $("#sortby").val("date"); $("#theform").submit(); });
    $("#sortby_event").click(function() { set_sort_direction(); $("#sortby").val("event"); $("#theform").submit(); });
    $("#sortby_category").click(function() { set_sort_direction(); $("#sortby").val("category"); $("#theform").submit(); });
    $("#sortby_user").click(function() { set_sort_direction(); $("#sortby").val("user"); $("#theform").submit(); });
    $("#sortby_cuser").click(function() { set_sort_direction(); $("#sortby").val("user"); $("#theform").submit(); });
    $("#sortby_group").click(function() { set_sort_direction(); $("#sortby").val("groupname"); $("#theform").submit(); });
    $("#sortby_pid").click(function() { set_sort_direction(); $("#sortby").val("patient_id"); $("#theform").submit(); });
    $("#sortby_success").click(function() { set_sort_direction(); $("#sortby").val("success"); $("#theform").submit(); });
    $("#sortby_comments").click(function() { set_sort_direction(); $("#sortby").val("comments"); $("#theform").submit(); });
    $("#sortby_checksum").click(function() { set_sort_direction(); $("#sortby").val("checksum"); $("#theform").submit(); });

    $('.datetimepicker').datetimepicker({
        <?php $datetimepicker_timepicker = true; ?>
        <?php $datetimepicker_showseconds = false; ?>
        <?php $datetimepicker_formatInput = true; ?>
        <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
        <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
    });
});

function set_sort_direction(){
    if($('#direction').val() == 'asc')
        $('#direction').val('desc');
    else
        $('#direction').val('asc');
}

function validatelog(){
    var this_button = $("#valid_button").button();
    this_button.button('loading');

    $.ajax({
        url:"../../library/log_validation.php",
        data: {
            csrf_token_form: <?php echo js_escape(CsrfUtils::collectCsrfToken()); ?>
        },
        asynchronous : true,
        method: "post",
        success :function(response){
            alert(response);
            this_button.button('reset');
        },
        failure :function(){
            alert(<?php echo xlj("Audit Log Validation Failed"); ?>);
            this_button.button('reset');
        }
    });

}
</script>

</body>
</html>
