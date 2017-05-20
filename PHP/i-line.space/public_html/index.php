<?php
session_start();
error_reporting(E_ERROR);
ini_set('display_errors', 0);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);



function get_client_ip() {
    $ipaddress = '';
    if (getenv('HTTP_CLIENT_IP'))
        $ipaddress = getenv('HTTP_CLIENT_IP');
    else if(getenv('HTTP_X_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    else if(getenv('HTTP_X_FORWARDED'))
        $ipaddress = getenv('HTTP_X_FORWARDED');
    else if(getenv('HTTP_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    else if(getenv('HTTP_FORWARDED'))
       $ipaddress = getenv('HTTP_FORWARDED');
    else if(getenv('REMOTE_ADDR'))
        $ipaddress = getenv('REMOTE_ADDR');
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

function ip2long_v6($ip) {
    $ip_n = inet_pton($ip);
    $bin = '';
    for ($bit = strlen($ip_n) - 1; $bit >= 0; $bit--) {
        $bin = sprintf('%08b', ord($ip_n[$bit])) . $bin;
    }

    if (function_exists('gmp_init')) {
        return gmp_strval(gmp_init($bin, 2), 10);
    } elseif (function_exists('bcadd')) {
        $dec = '0';
        for ($i = 0; $i < strlen($bin); $i++) {
            $dec = bcmul($dec, '2', 0);
            $dec = bcadd($dec, $bin[$i], 0);
        }
        return $dec;
    } else {
        trigger_error('GMP or BCMATH extension not installed!', E_USER_ERROR);
    }
}

function getData($IP, $IPversion) {
	require_once "./dbaccess.php";
	
	unset($_POST['search']);
		
	if ($IPversion == 4){
		$select = "select distinct(IPV4_SERVER_NAME), IPV4_MASK, IPV4_CCTLD, IPV4_TLD from IPV4_ILINES where IPV4_NETWORK_START <= ". $IP . " and IPV4_NETWORK_STOP >= " . $IP ." group by IPV4_SERVER_NAME order by IPV4_CCTLD, IPV4_TLD desc;";
		$rowNameServer =  "IPV4_SERVER_NAME";
		$rowNameMask =  "IPV4_MASK";
	}
	elseif ($IPversion == 6){
		$select = "select distinct(IPV6_SERVER_NAME), IPV6_MASK, IPV6_CCTLD, IPV6_TLD from IPV6_ILINES where IPV6_NETWORK_START <= ". $IP . " and IPV6_NETWORK_STOP >= " . $IP ." group by IPV6_SERVER_NAME order by IPV6_CCTLD, IPV6_TLD desc;";
		$rowNameServer =  "IPV6_SERVER_NAME";
		$rowNameMask =  "IPV6_MASK";
	}
	else{		
		echo "DEBUG: jakas lipa <br/>";
	}

		$connection = @new mysqli($host, $db_user, $db_password, $db_name);
		
		if($connection->connect_errno!=0){
			
			echo "Error: " .$connection->connect_errno . "More info: ". $connection->connect_error;
			echo "Something went wrong...";
		}
		else{
			
			
			mysqli_query($connection,"UPDATE STATS SET STATS_QUERY=STATS_QUERY+1;");
			$found_row = false;
	
			if($stid = @$connection->query("$select")){
				
				while ($row = mysqli_fetch_array($stid, MYSQLI_ASSOC)){
				$found_row = true;						
				$SERVER_NAME =  $row["$rowNameServer"];
				$MASK = $row["$rowNameMask"];

				echo "<tr><td class='text-left'>$SERVER_NAME</td><td class='text-right'>$MASK</td></tr>";
				//echo "</table>";
				}
			}
			if ($found_row == false) {
				echo "<SERVER>No results</SERVER>";
			}
	}
	$connection->close();
}

function getStats(){
	include ("dbaccess.php");
	
	$connection = @new mysqli($host, $db_user, $db_password, $db_name);

	if($connection->connect_errno!=0){
		echo "Unknown";
	}
	else{
		$result = mysqli_fetch_assoc(mysqli_query($connection, "select STATS_QUERY from STATS"));
		$statsCount = $result['STATS_QUERY'];
		return $statsCount;
	}
	$connection->close();
}

?>
<!DOCTYPE html>
<html>
<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title>IRC I-Line</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
	<meta name="description" content="IRCNet I-line Search Engine. Lists IRCNet servers available for your IPv4 / IPv6.">
	<link rel="icon" type="image/png" href="./favicon.ico" />
	<link rel="stylesheet" type="text/css" href="css/style03107.css"/>
	<link href="https://i-line.space/css/fonts.css" rel="stylesheet"> 
</head>
<body>
	<div class="logo">
		<div class="ircnet">IRCNet</div>
		<div class="iline">I-line Search Engine</div>
	</div>
	<form class="form-wrapper" method="POST" name="search" action="/"  enctype="multipart/form-data"><input type="text" name="search" maxlength="128" placeholder="IPv4 / IPv6" required/><button type="submit">Search</button>
	</form>
<?php

//#MAINTENECA

//header("Location: https://i-line.space/maintenance.html");

if(isset($_POST['search'])) {
	
	$IP = $_POST['search'];
	$IP = htmlentities($IP, ENT_QUOTES, "UTF-8");
	$IP = preg_replace('/\s+/', '', $IP);
	unset($_POST['search']);	
	
	
	// IPv6 parser
	if(filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
		
		if(preg_match('/^::*|^2000\:*|^FC00\:*|^FE80\:*|^FF00\:*|^::ffff:0:0\w*$|^100\:*|^64:ff9b\:*/i', $IP)){
		echo "<SERVER>Oh come on... give me some public address</SERVER>";
	}else{
		
echo <<< EOT

	I-lines for IPv6: $IP <br/><br/>

	<table class="table-fill">
		<thead>
		<tr>
		<th class="text-header">IRC Server</th>
		<th class="text-header">Matched network</th>
		</tr>
		</thead>

EOT;
		
		$IPv6 = ip2long_v6($IP);
		getData($IPv6, 6);
		
	}
	}
	elseif(filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		
		if(preg_match('/^127\.\d{1,3}\.\d{1,3}\.\d{1,3}$|^128\.\d{1,3}\.\d{1,3}\.\d{1,3}$|^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$|^192.168\.\d{1,3}\.\d{1,3}$|^255\.\d{1,3}\.\d{1,3}\.\d{1,3}$|^0\.\d{1,3}\.\d{1,3}\.\d{1,3}$|^169.254\.\d{1,3}\.\d{1,3}$|^224\.\d{1,3}\.\d{1,3}\.\d{1,3}$|^240\.\d{1,3}\.\d{1,3}\.\d{1,3}$|^172.(16|17|18|19|20|21|22|23|24|25|26|27|28|29|30|31)\.\d{1,3}\.\d{1,3}$|^169.254\.\d{1,3}\.\d{1,3}$|^100.(6(4|5|6|7|8|9)|((7|8|9)[0-9])|(1(0|1)[0-9])|12(0|1|2|3|4|5|6|7))\.\d{1,3}\.\d{1,3}$|^192.88.99.\d{1,3}$|^198.18\.\d{1,3}\.\d{1,3}$|^198.51.100.\d{1,3}$|^203.0.113.\d{1,3}$/i', $IP)){
		echo "<SERVER>Oh come on... give me some public address</SERVER>";
		}else{

echo <<< EOT

	I-lines for IPv4: $IP <br/><br/>
	<table class="table-fill">
		<thead>
		<tr>
		<th class="text-header">IRC Server</th>
		<th class="text-header">Matched network</th>
		</tr>
	</thead>

EOT;

		$IPv4 = ip2long($IP);
		getData($IPv4, 4);	
	}
	}
	else {
		echo "Invalid IP, please try again<br/><br/>
		Example:<br/> 
		IPv4: 127.0.0.1 <br/>
		IPv6: 2001:db8:0:2::1<br/>";
	}
}else{

	$IP = get_client_ip();
	
	if ("$IP" != "UNKNOWN"){

		$IP = htmlentities($IP, ENT_QUOTES, "UTF-8");
		$IP = preg_replace('/\s+/', '', $IP);

		// IPv6 parser
		if(filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
			
	echo <<< EOT
		I-lines for your IPv6: $IP <br/><br/>
		<table class="table-fill">
			<thead>
			<tr>
			<th class="text-header">IRC Server</th>
			<th class="text-header">Matched network</th>
			</tr>
			</thead>

EOT;
			
			$IPv6 = ip2long_v6($IP);
			getData($IPv6, 6);
			
		}
		elseif(filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {

	echo <<< EOT
		I-lines for your IPv4: $IP <br/><br/>
		<table class="table-fill">
			<thead>
			<tr>
			<th class="text-header">IRC Server</th>
			<th class="text-header">Matched network</th>
			</tr>
		</thead>

EOT;

			$IPv4 = ip2long($IP);
			getData($IPv4, 4);	
		}
	}else{
	
		echo <<< EOT

		Unable to resolve your IP, sorry:( <br/><br/>

EOT;
		
	}
}
?>
</table>
<div id="stats"><p class="stats" align="center">Number of queries: <?php echo getStats(); ?> </p></div>
<div id="footer"><p class="footer" align="center">© pbl@IRCNet, 2017</p></div>
</body>
</html>