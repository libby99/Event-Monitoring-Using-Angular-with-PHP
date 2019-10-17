<?php
header("Access-Control-Allow-Origin: *", false);
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: *");


function encryptAES($str, $key, $SESSID) {
    $iv="";
    for ($i=0; $i<16; $i++) $iv.=chr(rand(48,122));

    if (strlen($str)%16==0) $val = 16;                                                      // figure out how much padding is required
    else $val = (16-intval(strlen($str)%16));
    for ($i=0; $i<$val; $i++) $str.=chr($val);                                              // pad with ascii character of value

    $td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');                 // encrypt
    @mcrypt_generic_init($td, $key, $iv);
    $crypt_text = mcrypt_generic($td, $str);

    @mcrypt_generic_deinit($td);                                                            // close encryption
    mcrypt_module_close($td);

    $tohex = strToHex($iv.$crypt_text);                                                     // add iv

    return $SESSID.$tohex;                                                                  // convert to hex and send back
}

function convert_from_hex($h) {
    $r="";
    for ($i=0; $i<strlen($h); $i+=2) if ((isset($h[$i])) && (isset($h[$i+1]))) $r.=chr(hexdec($h[$i].$h[$i+1]));
    return $r;
}

function strToHex($str) {
    // convert ascii string to hex
    $hex = "";
    for ($i=0; $i<strlen($str); $i++) {
        $val = strtoupper(dechex(ord($str[$i])));
        $hex .= (strlen($val) == 1) ? "0".$val : $val;
    }
    return $hex;
}

function decryptAES($crypt_text, $key) {

    $crypt_text=convert_from_hex($crypt_text);                                              // convert from hex

    $iv = substr($crypt_text, 0, 16);                                                       // extract iv
    $crypt_text = substr($crypt_text, 16);                                                  // extract iv

    $td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');                 // decrypt
    @mcrypt_generic_init($td, $key, $iv);
    $package = @mdecrypt_generic($td, $crypt_text);

    mcrypt_generic_deinit($td);                                                             // close encryption
    mcrypt_module_close($td);

    $padqty=ord($package[strlen($package)-1]);                                              // remove padding

    return substr($package, 0, strlen($package)-$padqty);
}

function runQuery($url, $params, &$Sequence, $AESKEY, $SESSID) {

    $encryptedParams = ($AESKEY) ? encryptAES($params."&Sequence=".$Sequence, $AESKEY, $SESSID) : $params;
//   	echo $url; echo " &nbsp; &nbsp; "; echo $encryptedParams;
    $response = file_get_contents($url."?".$encryptedParams);

    // increment sequence number
    $Sequence++;

    $val = ($AESKEY) ? decryptAES($response, $AESKEY) : $response;
    return $val;
}

function xorFn($pswd, $num) {
    // xor two strings
    $numbin = str_pad(decbin($num), 32, "0", STR_PAD_LEFT);
    $startpos = strlen($numbin);

    $retval = "";
    for ($i=0; $i<strlen($pswd); $i++) {
        $charcode = ord($pswd[$i]);                                         // take character at position i

        $startpos = ($startpos==0) ? (strlen($numbin)-8) : ($startpos-8);   // grab 8 bits (if at lhs of string, start again at rhs)
        $comp = bindec(substr($numbin, $startpos, 8));                      // convert 8 bits to decimal

        $hexcode = dechex($charcode^$comp);                                 // xor character and 8 bits
        if (strlen($hexcode)==1) $hexcode = "0".$hexcode;                   // if string='F' then return '0F'
        $retval.=$hexcode;
    }

    return strtoupper($retval);
}

function login($loginDetails, &$Sequence) {

    // 1. Client generates a 32 character SessionID.
    $SESSID = "";

    for ($i=0; $i<32; $i++) {
        $tval = dechex(rand(0, 15));
        $SESSID .= strtoupper($tval);
    }

    // 2. User enters username and password.

    // 3. Client creates SHA hash of password - we'll call this pswdhash.
    $pswdhash = sha1($loginDetails['Password']);

    // 4. Client requests random number from DLL `Command&Type=Session&SubType=InitSession&SessionID=<SessionID>`.
    // 5. DLL returns random number.
    $data = runQuery("http://".$loginDetails['IPAddress']."/PRT_CTRL_DIN_ISAPI.dll", "Command&Type=Session&SubType=InitSession&SessionID=".$SESSID, $Sequence, null, $SESSID);

    // 6. Client creates XOR of username and (random number+1).
    $xorusername = xorFn($loginDetails['Username'], ($data+1));

    // 7. Client generates SHA hash of XOR - we'll call this hashxorusername.
    $hashxorusername = strtoupper(sha1($xorusername));

    // 8. Client creates XOR of pswdhash and random number.
    $xorpswdhash = xorFn($pswdhash, $data);

    // 9. Client generates SHA hash of XOR - we'll call this hashxorpswdhash.
    $hashxorpswdhash = strtoupper(sha1($xorpswdhash));

    // 10. Client sends hashxorusername, hashxorpswdhash and SessionID to DLL `Command&Type=Session&SubType=CheckPassword&Name=<Username>&Password=<Password>&SessionID=<SessionID>`.
	   // 11. DLL returns "FAIL" if Username or Password don't match, or a second random number if successfull.
    $data = runQuery("http://".$loginDetails['ControllerIPAddress']."/PRT_CTRL_DIN_ISAPI.dll", "Command&Type=Session&SubType=CheckPassword&Name=".$hashxorusername."&Password=".$hashxorpswdhash."&SessionID=".$SESSID, $Sequence, null, $SESSID);
    if ($data == "FAIL") return "FAIL";
    else {
        // 12. Client creates XOR of pswdhash and second random number.
        $xorpswdhash = xorFn($pswdhash, $data);

        // 13. Client generates SHA hash of XOR, and use first 16 characters as AES key.
        $hashxorpswdhash = sha1($xorpswdhash);

        //  14. Client stores the AES Key, SessionID and sets Sequence Number to 0.
        $AESKEY = strtoupper(substr($hashxorpswdhash, 0, 16));

        return array("AESKEY"=> $AESKEY, "SESSID"=>$SESSID);
    }

}

	$test = json_decode(file_get_contents("php://input"), true);


	$seq = 0;
	$data = login($test, $seq);
	$data["seq"]=$seq;
	$jsonData = json_encode($data);
 	
	print ($jsonData) ;
return;
?>