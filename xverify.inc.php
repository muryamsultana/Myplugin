<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require_once 'XverifyClientAPI.php';

define('XVERIFY_IP_CHECK_INTERVAL', 60 * 60 * 24); //24 hours

define('XVERIFY_LOG_TABLE', $wpdb->prefix . "Xverify_Log");

function getEmailSearch($email, $page = 1, $limit = 50){
    $page=1;
    $max = 50;
    $start = ($page - 1) * $max;
    $end = $max;
     $limit_str = " limit $start,$end";
    
    $XVERIFY_LOG_TABLE = XVERIFY_LOG_TABLE;   
    $query = "select * from $XVERIFY_LOG_TABLE where Email='$email' order by Created_Timestamp DESC  $limit_str";
    
    global $wpdb;
    $result = $wpdb->get_results($query);
    return $result;
}

function getReport_daterange($ts_start, $ts_end, $page = 1, $limit = 50) {
    
    $max = $limit;
    $start = ($page - 1) * $max;
    $end = $max;

    $limit_str = " limit $start,$end";

    $XVERIFY_LOG_TABLE = XVERIFY_LOG_TABLE;
    //$ts_start = $start;
    //$ts_start = date('Y-m-d H:i:s', $ts_start);
   // $ts_end = $end;
   // $ts_end = date('Y-m-d H:i:s', $ts_end);
    
    
    $query = "select * from $XVERIFY_LOG_TABLE where Created_Timestamp>='$ts_start' AND Created_Timestamp<='$ts_end' order by Created_Timestamp DESC  $limit_str";
    
    global $wpdb;
    $result = $wpdb->get_results($query);
    return $result;
}

function getReport_total($ts_start, $ts_end) {
    

    $XVERIFY_LOG_TABLE = XVERIFY_LOG_TABLE;

    $query = "select count(*) as total from $XVERIFY_LOG_TABLE where Created_Timestamp>='$ts_start' AND Created_Timestamp<='$ts_end'";
    
    global $wpdb;
    $result = $wpdb->get_results($query);
    return $result;
}

function getReport_totalemail($email) {
    

    $XVERIFY_LOG_TABLE = XVERIFY_LOG_TABLE;

    $query = "select count(*) as total from $XVERIFY_LOG_TABLE where Email='$email'";
    
    global $wpdb;
    $result = $wpdb->get_results($query);
    return $result;
}

function getTotalStatusCount($ts_start, $ts_end,$status='valid'){
    $XVERIFY_LOG_TABLE = XVERIFY_LOG_TABLE;

    $query = "select count(*) as total from $XVERIFY_LOG_TABLE where Created_Timestamp>='$ts_start' AND Created_Timestamp<='$ts_end' AND Status='$status'";
    
    global $wpdb;
    $result = $wpdb->get_results($query);
    return $result;
}

function Xverify_email($email) {


    $has_ipinvalid_limitreached = has_invalidipattempt();
    if ($has_ipinvalid_limitreached) {
        return array(
            'status' => 'invalid',
            'responsecode_str' => 'Sorry, too many invalid email attemtps from your side today. Please try again tomorrow'
        );
    }

    $already_inlog = emailalreadyExist($email);
    if ($already_inlog) {
        return array(
            'status' => $already_inlog['Status'],
            'already' => true,
            'responsecode_str' => $already_inlog['Xverify_Response_Code']
        );
    }

    $api_key = esc_attr(get_option('xverify_key'));
    $start_time_xverifyapi = microtime(true); //date('Y:m:d H:i:s');
    $options = array();
    $options['type'] = 'json'; // API response type
    $options['domain'] = esc_attr(get_option('xverify_domain')); // Reruired your domain name 
    $client = new XverifyClientAPI($api_key, $options);

    $data = array();
    $data['email'] = $email;
    $client->verify('email', $data);

    $Xverify_Obj = $client->getReponseAsObject();
    $end_time_xverifyapi = microtime(true); //date('Y:m:d H:i:s');


    $auto_corrected = false;
    $auto_corrected_address = "";

    if (isset($Xverify_Obj->auto_correct)) {
        $auto_corrected = $Xverify_Obj->auto_correct->corrected;
        if ($auto_corrected === 'true') {
            $auto_corrected_address = $Xverify_Obj->auto_correct->address;
        }
    }

    $x_status = $Xverify_Obj->status;
    $xverify_response_valid = unserialize(stripslashes( (get_option('xverify_response_valid') )));
 
    $valid_responsecode_list = isset($xverify_response_valid)?$xverify_response_valid:array(3,9);
    array_push($valid_responsecode_list,1);
    //$valid_responsecode_list = [1, 3, 9];
    if (in_array($Xverify_Obj->responsecode, $valid_responsecode_list)) {
        $status = 'valid';
    } else {
        $status = 'invalid';
    }

    //ad in log DB entry
    addinLog($status, $email, $Xverify_Obj);

    return array(
        'status' => $status, // valid , invalid 
        'responsecode' => $Xverify_Obj->responsecode, // 1 is for valid,  list is http://screencast.com/t/whmtHIKuA
        'auto_corrected' => $auto_corrected,
        'auto_corrected_address' => $auto_corrected_address,
        'responsecode_str' => Xverify_stringifyResponseCode($Xverify_Obj->responsecode),
        'xverify_status' => $x_status
    );
}

function has_invalidipattempt() {
    $ip = get_UserIP();
    $limit = "0," . esc_attr( get_option('xverify_invalid_ip_attempts') );
    $ts = date('Y-m-d H:i:s');
    $ts = strtotime($ts);
    $ts-=XVERIFY_IP_CHECK_INTERVAL;
    $ts = date('Y-m-d H:i:s', $ts);

    $XVERIFY_LOG_TABLE = XVERIFY_LOG_TABLE;
    $query = "select status from $XVERIFY_LOG_TABLE where Client_IP='$ip' and Created_Timestamp>='$ts' order by Created_Timestamp DESC limit $limit";

    global $wpdb;
    $result = $wpdb->get_results($query);

    $count_invalid = 0;
    if ($result > 0) {
        foreach ($result as $row) {
            if ($row->status=== 'invalid') {
                $count_invalid++;
            }
        }
    }

    if ($count_invalid >= esc_attr( get_option('xverify_invalid_ip_attempts') )) {
        // invalid attempt limit reached!
        return true;
    }

    return false;
}

function emailalreadyExist($email) {
    $email = sanitize_text_field($email);

    $XVERIFY_LOG_TABLE = XVERIFY_LOG_TABLE;
    $query = "select Status,Xverify_Response_Code from $XVERIFY_LOG_TABLE where Email='$email' order by Created_Timestamp DESC";

    global $wpdb;
    $result = $wpdb->get_row($query, ARRAY_A);

    if ($result) {
        $obj = $result;
        return $obj;
    }

    return false;
}

function addinLog($status, $email, $obj) {
    $XVERIFY_LOG_TABLE = XVERIFY_LOG_TABLE;

    $ip = get_UserIP();
    $ip_int = ip2long($ip);
    $page = $_SERVER['REQUEST_URI'];
    $page = $page ? $page : $_SERVER['PHP_SELF'];
    $page = $page === 'UNKNOWN' ? $_SERVER['PHP_SELF'] : $page;
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $_SERVER['PHP_SELF'];
    $referer = sanitize_text_field($referer);
    $comments = "Request came from " . $referer; // test purpose

    $auto_corrected = false;
    $auto_corrected_address = "";

    if (isset($obj->auto_correct)) {
        $auto_corrected = $obj->auto_correct->corrected;
        if ($auto_corrected === 'true') {
            $auto_corrected_address = $obj->auto_correct->address;
            $comments = "User email is auto corrected. initially it was " . $email;
            $email = $auto_corrected_address;
        }
    }


    global $wpdb;
    $wpdb->query($wpdb->prepare(
                    "INSERT INTO $XVERIFY_LOG_TABLE (Email, Client_IP,Client_IP_Integer,Auto_Corrected, Status,Xverify_Status,Code,Xverify_Response_Code,Comments,Created_Timestamp) VALUES ( %s, %s,%d,%s, %s,%s, %s, %s,%s,%s )", array(
                $email,
                $ip,
                $ip_int,
                $auto_corrected,
                $status,
                $obj->status,
                $obj->responsecode,
                Xverify_stringifyResponseCode($obj->responsecode),
                $comments,
                date('Y-m-d H:i:s')
                    )
    ));
}

function Xverify_stringifyResponseCode($code) {
    $str = "";

    switch ($code) {
        case 1:
            $str = "Valid Email Address";
            break;
        case 2:
            $str = "Email Address Does Not Exist";
            break;
        case 3:
            $str = "Unknown";
            break;
        case 4:
            $str = "Fraud List";
            break;
        case 5:
            $str = "High Risk Email Address";
            break;
        case 6:
            $str = "Affiliate Is Blocked By Client";
            break;
        case 7:
            $str = "Complainer Email Address";
            break;
        case 8:
            $str = "Top Level Domain Blocked By Client";
            break;
        case 9:
            $str = "Temporary/Disposable Email";
            break;
        case 10:
            $str = "Keyword is Blocked By Client";
            break;
        case 11:
            $str = "IP address or Country Not Allowed";
            break;
        case 12:
            $str = "Block list from Client Settings";
            break;
        case 110:
            $str = "Email belongs to malicious domain";
            break;
        case 120:
            $str = "Email contains whitespaces at start or at end";
            break;
        case 400:
            $str = "Missing required fields";
            break;
        case 503:
            $str = "Invalid API Key/Service Not Active";
            break;
        case 15://custom
            $str = "Too many invalid email attemtps.";
            break;
    }

    return $str;
}

function get_UserIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if (isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if (isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if (isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if (isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';

    $ipaddress = explode(",", $ipaddress);
    $ipaddress = $ipaddress[0];

    return $ipaddress;
}
