<?php
include_once LIB_PATH."/lib/mysqlilib.php";

//短信類別的介面
interface ISMS{
	public function send($mobile, $content, $cell=null, $sendtime=null); //發送短信
	public function queryBalance(); //取得剩餘條數
	public function sendResultMapping($result);
}

/*
 * 凌凱短信物件
 * http://mb345.com:999/ws/Send2.aspx?&Mobile=*&Content=*&Cell=*&SendTime=*
 *  
 */
class SMS_mb345 implements ISMS{
	private $m_acc = null;
	private $m_pwd = null;
	private $m_ws_url = null;

	/**
	 * 建構子
	 * @param string $acc 帳號
	 * @param string $pwd 密碼
	 * @return void
	 */
	public function __construct($acc, $pwd){
		$this->m_acc = $acc;
		$this->m_pwd = $pwd;
		$this->m_ws_url = "http://mb345.com:999/WS/";
	}

	/**
	 * 發送短信
	 * @param string $mobile 手機號碼
	 * @param string $content 短信內容
	 * @param string $cell
	 * @param string $sendtime
	 */
	public function send($mobile, $content, $cell=null, $sendtime=null){
		$executeFile = "Send2.aspx";
		$params['CorpID'] = $this->m_acc;
		$params['Pwd'] = $this->m_pwd;
		$params['Mobile'] = $mobile;
		$params['Content'] = urlencode($content);
// 		$params['Content'] = $content;
		$params['Cell'] = $cell;
		$params['SendTime'] = $sendtime;
// 		return $this->m_builde($this->m_ws_url, $executeFile, $params);
		return $this->m_fetch( $this->m_builde($this->m_ws_url, $executeFile, $params) );
	}
	
	/**
	 * 取得剩餘條數
	 * @return string
	 */
	public function queryBalance(){
		$executeFile = "SelSum.aspx";
		$params['CorpID'] = $this->m_acc;
		$params['Pwd'] = $this->m_pwd;
		return $this->m_fetch( $this->m_builde($this->m_ws_url, $executeFile, $params) );
	}

	/**
	 * 請求及取得短信API結果 
	 * 
	 * @param string $url API請求網址
	 * @return string
	 */
	private function m_fetch($url){
		return file_get_contents($url);
	}

	/**
	 * 建構請求網址
	 * 
	 * @param string $url API請求網址
	 * @param string $executeFile API File
	 * @param array $params 參數陣列
	 * @return string
	 */
	private function m_builde($url, $executeFile, $params=array()){
		$dataArray = array();
		foreach($params as $key=>$item){
			$dataArray[] = sprintf("%s=%s", $key, $item);
		}
		return sprintf("%s%s?%s", $url, $executeFile, implode("&", $dataArray));
	}
	
	/**
	 * 发送成功（得到大于0的数字、作为取报告的id）
	 * -1、帐号未注册；-2、其他错误；-3、密码错误；-4、手机号格式不对；
	 * -5、余额不足；-6、定时发送时间不是有效的时间格式；
	 * -7、禁止10小时以内向同一手机号发送相同短信
	 * 
	 * @param string $result
	 * @return string
	 */
	public function sendResultMapping($result){
		$resultMapping["-1"] = "帳號未注冊";
		$resultMapping["-2"] = "其它錯誤";
		$resultMapping["-3"] = "密碼錯誤";
		$resultMapping["-4"] = "手機號碼格式不對";
		$resultMapping["-5"] = "餘額不足";
		$resultMapping["-6"] = "定時發送時間不是有效的時間格式";
		$resultMapping["-7"] = "禁止10小時以內向同一手機號發送相同短信";
		
		if($result > 0) {
			return "發送成功";
		}else{
			return $resultMapping[$result];
		}
		return "未定義的狀態";
	}
}

/**
 * 短信物件工廠
 */
class SMS_Factory{
	
	/**
	 * 
	 * @param string $telecom_id 短信業者ID
	 * @param string $acc 帳號
	 * @param string $pwd 密碼
	 * @return SMS_mb345|NULL
	 */
	public static function forge($telecom_id, $acc, $pwd){
		switch($telecom_id){
			case '1': //凌凱
				return new SMS_mb345($acc, $pwd);
				break;
			default:
				return null;
		}
	}
}

/**
 * 待發送短信佇列類別
 */
class Queue{
	private static $m_instance = null;
	private $m_db = array();
	private $m_table = null;

	/**
	 * 建構子
	 */
	private function __construct(){
		$this->m_table = "sms_queue";
		$this->m_db["Cm"] = DB::forge("Cm");
		$this->m_db["Cs"] = DB::forge("Cs");
	}

	/**
	 * 取得短信佇列實例
	 * 
	 * @return Queue
	 */
	public static function forge(){
		if(null == self::$m_instance){
			self::$m_instance = new self();
		}
		return self::$m_instance;
	}

	/**
	 * 新增一筆到短信佇列Table
	 * 
	 * @param string $hall_id
	 * @param string $mid
	 * @param string $state
	 * @param string $send_num
	 * @return void
	 */
	public function insert($hall_id, $mid, $state=0, $send_num=0){
		if(!isset($hall_id) || !isset($mid) || $this->m_checkItemExists($hall_id, $mid)){
			return false;
		}
		$sql = sprintf("INSERT INTO `%s` SET `hall_id` = '%s', `mid` = '%s', `state` = '%s', `send_num` = '%s'",
				$this->m_table,
				$hall_id,
				$mid,
				$state,
				$send_num);
		$this->m_db["Cm"]->query($sql);
	}
	
	/*
	 * 更新短信佇列Table中，tuple 的資料
	 */
	public function update($id, $data){
		$setArray = array();
		foreach($data as $key => $value){
			if(in_array($key, array("hall_id", "mid", "state", "send_num", "isDeal", "send_result", "last_send_dt"))){
				$setArray[] = sprintf("`%s` = '%s'", $key, $value);
			}
		}
		
		$sql = sprintf("UPDATE `%s` SET %s WHERE `id` = '%s'",
				$this->m_table,
				implode(", ", $setArray),
				$id
		);
		$this->m_db["Cm"]->query($sql);
	}
	
	/*
	 * 更新 tuple 的「排程處理中」的狀態
	 */
	public function updateDealState($id, $isDeal){
		$sql = sprintf("UPDATE `%s` SET `isDeal` = '%s' WHERE `id` = '%s'",
				$this->m_table,
				$isDeal ? 1 : 0,
				$id
				);
		$this->m_db["Cm"]->query($sql);
	}

	/*
	 * 判定此 tuple 是否存在
	 */
	private function m_checkItemExists($hall_id, $mid){
		$sql = sprintf("SELECT COUNT(*) as count FROM `%s` WHERE `hall_id` = '%s' AND `mid` = '%s'",
				$this->m_table,
				$hall_id,
				$mid);
		$this->m_db["Cs"]->query($sql);
		$this->m_db["Cs"]->next_record();
		return $this->m_db["Cs"]->f("count") > 0 ? true : false;
	}

	/*
	 * 取得短信佇列資料集
	 * 待發送短信佇列 的撈出條件應為：未發送(state=0), 非處理中(isDeal=0)
	 */
	public function getDataRows(){
		$sql = sprintf("SELECT * FROM `%s` WHERE `state` ='0' AND `isDeal` = '0' ORDER BY `hall_id`", $this->m_table);
		$this->m_db["Cs"]->query($sql);
		$rows = $this->m_db["Cs"]->get_total_data();
		return $rows;
	}
	
	/*
	 * 
	 */
	public function find($hall_id, $mid){
		$sql = sprintf("SELECT * FROM `%s` WHERE `hall_id` = '%s' AND `mid` = '%s'", 
						$this->m_table, 
						$hall_id,
						$mid);
		$this->m_db["Cs"]->query($sql);
		$this->m_db["Cs"]->next_record();
		return QueueItem::forgeByRow($this->m_db["Cs"]->record);
	}
	
	public function checkDeal(){
		$sql = sprintf("SELECT COUNT(*) count FROM `%s` WHERE `isDeal` = '1'", $this->m_table);
		$this->m_db["Cs"]->query($sql);
		$this->m_db["Cs"]->next_record();
		return $this->m_db["Cs"]->f("count") > 0 ? true : false;
	}
}


class QueueItem{
	
	private $m_data_row = array();
	
	private function __construct($row){
		$this->m_data_row = $row;
	}
	
	public static function forgeByRow($row){
		return new self($row);
	}
	
	public function fetch($name){
		return isset($this->m_data_row[$name]) ? $this->m_data_row[$name] : null;
	}
	
	public function check(){
		if(isset($this->m_data_row["id"]) && isset($this->m_data_row["hall_id"]) && isset($this->m_data_row["mid"])){
			return true;
		}
		return false;
	}
}

/*
 * 短信帳號列表
 */
class TeleAccList{

	private static $m_instance = array();
	private $m_db = array();
	private $m_table = null;
	private $m_hall_id = null;
	private $m_telecom_id = null;
	private $m_current = -1;
	private $m_point = -1;
	private $m_sms = null;
	private $m_acc_list = array();

	/*
	 * 建構子
	 */
	private function __construct($hall_id){
		$this->m_db["Cm"] = DB::forge("Cm");
		$this->m_db["Cs"] = DB::forge("Cs");		
		
		$this->m_table = "tele_acc";

		$this->m_hall_id = $hall_id;
		$this->m_acc_list = $this->m_getTeleAccList();
	}

	/**
	 * 取得某廳主的短信帳號列表實例
	 * 
	 * @param string $hall_id
	 * @return TeleAccList
	 */
	public static function forge($hall_id){
		if(isset($hall_id) && !isset(self::$m_instance[$hall_id])){
			self::$m_instance[$hall_id] = new self($hall_id);
		}
		return self::$m_instance[$hall_id];
	}

	/**
	 * 取得某廳主的短信帳號列表
	 * 
	 * @return array
	 */
	private function m_getTeleAccList(){
		$sql = sprintf("SELECT 
							* 
						FROM 
							`%s` 
						WHERE 
							`hall_id` = '%s' AND `is_enable` = '1' AND `balance` > '0'
						ORDER BY 
							`position` ASC",
				$this->m_table,
				$this->m_hall_id);
		$this->m_db["Cs"]->query($sql);
		$rows = $this->m_db["Cs"]->get_total_data();
		return $rows;
	}

	/**
	 * 取得短信帳號列表下一筆資料錄
	 * 
	 * @return array | NULL
	 */
	public function next(){
		if($this->m_hasNext()){
			return $this->m_acc_list[$this->m_current];
		}
		return null;
	}

	/**
	 * 判定是否有下一筆
	 * 
	 * @return boolean
	 */
	private function m_hasNext(){
		if( $this->m_current + 1 < count($this->m_acc_list)){
			$this->m_current++;
			return true;
		}
		return false;
	}
	
	/**
	 * 指標重整
	 * 
	 * @return TeleAccList
	 */
	public function renew(){
		$this->m_current = -1;
		return $this;
	}	
		
	/**
	 * 取得一筆有效的短信物件
	 * 
	 * @param string $force 是否強制取下一筆可用的短信物件
	 * @return ISMS | NULL
	 */
	public function nextAvailabeSMS($force=false){
		if(!$force && isset($this->m_sms) && $this->m_sms->queryBalance() > 0){
			return $this->m_sms;
		}

		while($this->m_hasNextPoint()){
			$row = $this->m_acc_list[$this->m_point];
			$this->m_sms = SMS_Factory::forge($row["telecom_id"], $row["acc"], $row["pwd"]);
			if($this->m_sms && $this->m_sms->queryBalance() > 0) {
				return $this->m_sms;
			}
		}
		return null;
	}

	/**
	 * 判定是否有下一筆
	 * 
	 * @return boolean
	 */
	private function m_hasNextPoint(){
		if( $this->m_point + 1 < count($this->m_acc_list)){
			$this->m_point++;
			return true;
		}
		return false;
	}
	
	/**
	 * 指標重整
	 * @return void 
	 */
	public function resetPoint(){
		$this->m_point = -1;
	}
}

/**
 * 使用者物件
 */
class SMS_User{
	private static $m_instance = null;
	private $m_id = null;
	private $m_user_id = null;
	private $m_name_real = null;
	private $m_telephone = null;
	private $m_password = null;

	/**
	 * 建構子
	 * @param string $id
	 * @return void
	 */
	private function __construct($id){
		$this->m_buildDetail($id);
		$this->m_buildPassword($id);
	}
	
	/**
	 * 取得使用者物件實例
	 * 
	 * @param string $id
	 * @return SMS_User
	 */
	public static function forge($id){
		if(!isset(self::$m_instance[$id])){
			self::$m_instance[$id] = new self($id);
		}
		return self::$m_instance[$id];
	}

	/**
	 * 建立使用者資料
	 * 
	 * @param string $id
	 * @return void
	 */
	private function m_buildDetail($id){
		$data = DurianConn::Connect()->getUserDetail($id);
		if(isset($data["result"]) && $data["result"]=="ok"){
			$this->m_id = $data["ret"]["id"];
			$this->m_user_id = $data["ret"]["user_id"];
			$this->m_name_real = $data["ret"]["name_real"];
			$this->m_telephone = $data["ret"]["telephone"];
		}
	}

	/**
	 * 藉由 DurianConn 取得使用者密碼
	 * 
	 * @param string $id
	 * @return void
	 */
	private function m_buildPassword($id){
		$data = DurianConn::Connect()->getPassword($id);
		if(isset($data["result"]) && $data["result"]=="ok"){
			$this->m_password = $data["ret"];
		}
	}

	/**
	 * 取得使用者電話
	 * @return string  
	 */
	public function getTelephone(){
		return $this->m_telephone;
	}

	/**
	 * 取得使用者密碼
	 * @return string 
	 */
	public function getPassword(){
		return $this->m_password;
	}
}

class Hall{
	private static $m_map = array();
	private static $m_instance = array();
	private $m_hall_id = null;
	
	private function __construct($hall_id){
		if(!count(self::$m_map)){
			self::$m_map = self::m_getHallMapping();
		}
		$this->m_hall_id = $hall_id;
	}
	
	/**
	 * @param string $hall_id
	 * @return Hall
	 */
	public static function forge($hall_id){
		if(!isset(self::$m_instance[$hall_id])){
			self::$m_instance[$hall_id] = new self($hall_id);
		}	
		return self::$m_instance[$hall_id];
	}
	
	private static function m_getHallMapping(){
		$halllist = DurianConn::Connect()->domain_list(1,1,1);
		$data = array();
		foreach($halllist['ret'] as $item){
			$data[$item["id"]] = $item;
		}
		return $data;
	}
	
	/**
	 * 
	 * @param string $key
	 * @return string | NULL
	 */
	public function fetch($key){
		return isset(self::$m_map[$this->m_hall_id][$key]) ? self::$m_map[$this->m_hall_id][$key] : null; 
	}
	
}

/**
 * 小幫手
 */
class Help{
	/**
	 * 
	 * @param string $pwd
	 * @param string $alias
	 * @return string
	 */
	public static function msg($pwd=null, $alias=null){
		$str = sprintf("重發密碼：%s 【%s】", $pwd, $alias);
		return iconv("UTF-8", "GBK//IGNORE", $str);
	}
}


class TeleAccTable{

	private static $m_instance = null;
	private $m_db = array();
	private $m_table = null;
	private $m_acc_list = array();
	private $m_hall_id = null;
	private $m_telecom_id = null;
	private $m_current = -1;
	private $m_sms = null;

	/*
	 * 建構子
	 */
	private function __construct(){
		$this->m_db["Cm"] = DB::forge("Cm");
		$this->m_db["Cs"] = DB::forge("Cs");
		$this->m_table = "tele_acc";
		$this->m_acc_list = $this->m_getTeleAccList();
	}

	public static function forge(){
		if(null == self::$m_instance){
			self::$m_instance = new self();
		}
		return self::$m_instance;
	}
	
	private function m_getTeleAccList(){
		$sql = sprintf("SELECT * FROM `%s` ORDER BY `hall_id`", $this->m_table);
		$this->m_db["Cs"]->query($sql);
		$rows = $this->m_db["Cs"]->get_total_data();
		return $rows;
	}
	
	/**
	 * 取得短信帳號列表下一筆資料錄
	 *
	 * @return array | NULL
	 */
	public function next(){
		if($this->m_hasNext()){
			return $this->m_acc_list[$this->m_current];
		}
		return null;
	}
	
	/**
	 * 判定是否有下一筆
	 *
	 * @return boolean
	 */
	private function m_hasNext(){
		if( $this->m_current + 1 < count($this->m_acc_list)){
			$this->m_current++;
			return true;
		}
		return false;
	}	
	
	/**
	 * 指標重整
	 *
	 * @return TeleAccList
	 */
	public function renew(){
		$this->m_current = -1;
		return $this;
	}	
	
	public function updateBalance($row){
		$sms = SMS_Factory::forge($row["telecom_id"], $row["acc"], $row["pwd"]);
		if(!$sms){
			return false;
		}
		$balance = $sms->queryBalance();
		$bb = $balance > 0 ? $balance : 0;
			
		$sql = sprintf("UPDATE `%s` SET `balance` = '%s' WHERE `id` = '%s'",
						$this->m_table, $bb, $row["id"]);
			
 		$this->m_db["Cm"]->query($sql);
	}
}

class DB{
	private static $m_instance = null;
	
	private function __construct(){
		self::$m_instance["Cm"] = new proc_DB(DB_HOST_C_M,DB_USER_C,DB_PWD_C,DB_NAME_C);
		self::$m_instance["Cs"] = new proc_DB(DB_HOST_C_S,DB_USER_C,DB_PWD_C,DB_NAME_C);
	}
	
	public static function forge($name){
		if(null == self::$m_instance){
			new self();
		}
		return isset(self::$m_instance[$name]) ? self::$m_instance[$name] : null;
	}
}
