<?php
require_once('config.php');

$aryenvkey = array(
"HTTP_USER_AGENT" =>1
,"REMOTE_ADDR" =>1
,"REMOTE_PORT" =>1
,"SERVER_SOFTWARE" =>1
,"REQUEST_METHOD" =>1
,"QUERY_STRING" =>1
,"REQUEST_URI" =>1
,"SCRIPT_NAME" =>1
,"SCRIPT_FILENAME" =>1);
//--------------------------------------------------------------------------------
//�K��N���X
class BaseMySqlConnection
{
	var $m_dbconnection = "0";
	var $m_rscount = 0;
	var $m_aryrs;
	var $m_connect = false;
	var $m_transaction = false;
	var $m_totalerror ="";
	var $m_bManualCommit = false;
	var $m_bBegin = false;
	var $m_selectdb = "0";

	function __construct($bManualCommit = false) {
		$this->m_bManualCommit = $bManualCommit;
		$this->ConnectStart();
		if(!$this->m_bManualCommit)
			$this->beginT();
	}

	//�蓮�g�����U�N�V�����X�^�[�g
	function begin() {
		$this->m_transaction = true;
		$this->beginT();
	}

	//�g�����U�N�V��������
	private function beginT() {
		$this->execute("BEGIN;");
		$this->m_bBegin = true;
	}

	//�g�����U�N�V�����m��
	function commit() {
		if(!$this->m_bBegin) return;
		$bError = $this->TransactionErrorCheck();
		if(!$bError){
			$this->execute("COMMIT;");
			$this->execute("END;");
		} else {
			$this->rollback();
			$h = fopen(S_PATH_QUERYERROR."/ERROR_DB_".date("Ymd").".txt","a");
			fwrite($h,preg_replace("/\r\n|\r|\n/","\n",$this->m_totalerror));
			fclose($h);
			echo 'QueryError';
		}
		if($this->m_transaction && !$this->m_bManualCommit)
			$this->beginT();
		$this->m_transaction = false;
	}

	//	���[���o�b�N
	function rollback() {
		if(!$this->m_bBegin) return;
		$this->execute("ROLLBACK;");
		$this->execute("END;");
		if( $this->m_transaction && !$this->m_bManualCommit ) {
			$this->beginT();
		}
		$this->m_transaction = false;
	}

	function ConnectStart() {
		$this->m_dbconnection = mysql_connect(S_DB_HOST, S_DB_USER, S_DB_PSWD);
		mysql_query("SET NAMES utf8", $this->m_dbconnection);
		// �f�[�^�x�[�X�̑I��
		$this->m_selectdb = mysql_select_db(S_DB_NAME, $this->m_dbconnection);
		if(isset($this->m_dbconnection)) {
			$this->m_connect = true;
		}

		return $this->m_dbconnection;
	}

	function TransactionErrorCheck() {
		global $aryenvkey;
		if( mysql_error($this->m_dbconnection) ) {
			$this->m_totalerror = mysql_error($this->m_dbconnection)."\n";
			$bError = true;
		}

		for( $i = 1; $i <= $this->m_rscount;++$i ) {
			if($this->m_aryrs[$i]->m_error) {
				$bError = true;
				$this->m_totalerror .= $this->m_aryrs[$i]->m_errmsg."\n";

			}
		}

		if(!empty($bError)) {
			$sBuff = date("Y/m/d H:i")."\n";
			foreach($_SERVER as $k => $v) {
				if(array_key_exists($k,$aryenvkey))
					$sBuff .= $k." => ".$v."\n";
			}
			$this->m_totalerror = $sBuff.$this->m_totalerror;
			return $bError;
		}

	}

	function ConnectClose() {
		if($this->m_connect) {
			if($this->m_transaction) {
				$this->rollback();
			} else {
				$this->commit();
			}
			mysql_close($this->m_dbconnection);
			$this->m_connect = false;
		}
	}

	function __destruct() {
		foreach($this->m_aryrs as $id => $obj) {
			$obj->free();
			unset($this->m_aryrs[$id]);
		}
		//$this->ConnectClose();
	}

	function sqlenc($swkValue) {
		$swkValue = mb_convert_encoding($swkValue, DB_ENCODE, IF_ENCODE);
		$swkValue = @mysql_escape_string($swkValue);
		$swkValue = mb_convert_encoding($swkValue, IF_ENCODE, DB_ENCODE);
		return $swkValue;
	}

	function execute($sQuery) {
		$this->m_aryrs[++$this->m_rscount] = new MySqlRecordControl($this->m_dbconnection,$sQuery);
	}

	function rsexec($sQuery) {
		$this->m_aryrs[++$this->m_rscount] = new MySqlRecordControl($this->m_dbconnection,$sQuery);
		return $this->m_aryrs[$this->m_rscount];
	}

	function is_error(){
		$bError = $this->TransactionErrorCheck();
		return $bError;
	}
}

//PostgreSQLRecordControl
class MySqlRecordControl
{
	var $m_objrs = "0";
	var $m_query = "0";
	var $m_line = "";
	var $m_error = false;
	var $m_errmsg = "";

	function __construct($dbConnection,$wkQuery) {
		$this->m_query = mb_convert_encoding($wkQuery, DB_ENCODE, IF_ENCODE);
		$this->m_objrs = @mysql_query($this->m_query, $dbConnection);
//		echo $wkQuery."<br>";
		if($this->m_objrs) {
			$this->next();
		} else {
			$this->m_error = true;
			$this->m_errmsg.= 'query='.$this->m_query."\n";
			$this->m_errmsg.= 'pg_last_error='.@mysql_error($dbConnection)."\n";
		}

		return $this->m_objrs;
	}

	function line() {
		return $this->m_line;
	}

	function next() {
		$this->m_line = @mysql_fetch_array($this->m_objrs, MYSQL_ASSOC);
		if(is_array($this->m_line)){
			foreach($this->m_line as $key => $value ) {
				$this->m_line[$key] = mb_convert_encoding($value, IF_ENCODE, DB_ENCODE);
			}
		}
	}

	function iseof() {
		if($this->m_line) {
			return false;
		} else {
			return true;
		}
	}

	function free() {
		if($this->m_objrs) {
			$result = @mysql_free_result($this->m_objrs);
			$this->m_objrs = NULL;
			return $result;
		}
	}
	function __destruct() {
		$this->free();
	}

	function count() {
		return mysql_num_rows($this->m_objrs);
	}
}

//�h���N���X
class DBConnection extends BaseMySqlConnection
{
	function __construct($bManualCommit = false) {
		parent::__construct($bManualCommit);
	}

	function __destruct() {
		// ���N���X�j��
		parent::__destruct();
	}

	function sqlenc($wkSql) {
		$return = parent::sqlenc($wkSql);
		return $return;
	}

	function connect() {
		$return = parent::ConnectStart();
		return $return;
	}

	function close() {
		parent::ConnectClose();
		return true;
	}

	function rsexec($wkQuery) {
		$rs = parent::rsexec($wkQuery);
		return $rs;
	}

	function is_error() {
		$error = parent::is_error();
		return ($error)? true : false;
	}
}
?>