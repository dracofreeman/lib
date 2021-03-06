<?php
interface ISMS{
	public function send();
}

class SMS_mb345 implements ISMS{
	private $m_acc = null;
	private $m_pwd = null;
	private $m_ws_url = null;
	
	public function __construct($acc, $pwd){
		$this->m_acc = $acc;
		$this->m_pwd = $pwd;
		$this->m_ws_url = "http://mb345.com:999/WS/";
	}
	
	public function send(){
		
	}
	
	public function queryBalance(){
		$executeFile = "SelSum.aspx";
		$params['CorpID'] = $this->m_acc;
		$params['Pwd'] = $this->m_pwd;
		return $this->m_fetch( $this->m_builde($this->m_ws_url, $executeFile, $params) );
	}
	
	private function m_fetch($url){
		return file_get_contents($url);
	}
	
	private function m_builde($url, $executeFile, $params=array()){
		$dataArray = array();
		foreach($params as $key=>$item){
			$dataArray[] = sprintf("%s=%s", $key, $item);
		}
		return sprintf("%s%s?%s", $url, $executeFile, implode("&", $dataArray));
	}
}

class SMS{
	public static function forge($hid=null, $sms=null){
		return new SMS_mb345('LKSDK0001722', '946353');
	}
}


class KKDB{
	private static $m_instance = null;
	private $m_conn = null;
	
	private function __construct(){
		$this->m_conn = mysql_connect("localhost", "root", "");
		mysql_select_db("project_43425", $this->m_conn);
	}
	
	public static function forge(){
		if(null == self::$m_instance){
			self::$m_instance = new self();
		}
		return self::$m_instance;
	}
	
	public function query($sql){
		return mysql_query($sql, $this->m_conn);
	}
	
	public function fetchOne($sql){
		$query = $this->query($sql);
		return ($row = mysql_fetch_assoc($query)) ? $row : null;
	}
	
	public function fetchAll($sql){
		$query = $this->query($sql);
		$rows = array();
		while($row = mysql_fetch_assoc($query)){
			$rows[] = $row;
		}
		return $rows;
	}
	
	public function set_string($data){
		$col = array();
		foreach($data as $key => $item){
			$col[] = sprintf("%s='%s'", $key, $item);
		}
		return implode(", ", $col);
	}
}

class KKView{
	
	private $m_file = null;
	
	private function __construct($file){
		$this->m_file = $file;
	}
	
	public static function forge($file){
		return new self($file);
	}
	
	public function render(){
		file_exists($this->m_file) || die("file not existe"); 
		ob_start();
		include($this->m_file);
		return ob_get_clean();
	}
	
	public function __toString(){
		return $this->render();
	}
}

class KKInput{
	
	private $m_getArray = array();
	private $m_postArray = array();
	private $m_reqArray = array();
	private static $m_instance = null;
	
	private function __construct(){
		$this->m_getArray = $_GET;
		$this->m_postArray = $_POST;
		$this->m_reqArray = $_REQUEST;
	}
	
	public static function forge(){
		if(null == self::$m_instance){
			self::$m_instance = new self();
		}	
		return self::$m_instance;
	}
	
	public function get($name=null, $default=null){
		return  (isset($this->m_getArray[$name])) ? trim($this->m_getArray[$name]) : $default;
	}

	public function post($name=null, $default=null){
		return  (isset($this->m_postArray[$name])) ? trim($this->m_postArray[$name]) : $default;
	}
	
	public function req($name=null, $default=null){
		return  (isset($this->m_reqArray[$name])) ? trim($this->m_reqArray[$name]) : $default;
	}
}


class KKGrid{
	
	private static $m_instances = array();
	private $m_page = null;
	private $m_limit = null;
	private $m_sidx = null;
	private $m_sord = null;
	private $m_g = null;
	private $m_oper = null;
	private $m_id = null;
	private $m_total_pages = null;
	private $m_start = 0;
	private $m_count = 0;
	
	private function __construct(){
		$this->m_input = KKInput::forge();
		
		$this->m_page = $this->m_input->req("page", 0);
		$this->m_limit = $this->m_input->req("rows", 10);
		$this->m_sidx = $this->m_input->req("sidx", 1);
		$this->m_sord = $this->m_input->req("sord", "ASC");
		$this->m_g = $this->m_input->req("g", null);
		$this->m_oper = $this->m_input->req("oper", null);
		$this->m_id = $this->m_input->req("id", null);
		$this->m_totalrows = $this->m_input->req("totalrows", false);
		if($this->m_totalrows) {
			$this->m_limit = $this->m_totalrows;
		}
		
	}
	
	public static function forge($name){
		if(!isset(self::$m_instances[$name])){
			self::$m_instances[$name] = new self();
		}
		return self::$m_instances[$name];
	}
	
	public function build($count=0){
		if( $count >0 ) {
			$this->m_total_pages = ceil($count/$this->m_limit);
		} else {
			$this->m_total_pages = 0;
		}
		$this->m_count = $count;
		
		if ($this->m_page > $this->m_total_pages){
			$this->m_page = $this->m_total_pages;
		}
		
		$this->m_start = $this->m_limit * $this->m_page - $this->m_limit;
		if ($this->m_start < 0){
			$this->m_start = 0;
		}
		
		$this->out->page = $this->m_page;
		$this->out->total = $this->m_total_pages;
		$this->out->records = $this->m_count;
		
		return sprintf("ORDER BY %s %s LIMIT %s , %s", $this->m_sidx, $this->m_sord, $this->m_start, $this->m_limit);
	}
	
	public function getResult($data=array(), $primary="id"){
		$i=0;
		foreach($data as $row){
			foreach($row as $key => $item){
				$this->out->rows[$i]['id']=$row[$primary];
				$this->out->rows[$i]['cell'][] = $row[$key];
			}
			$i++;
		}
		return json_encode($this->out);		
	}
	
}


class KKString{
	public function sql_set($data){
		$col = array();
		foreach($data as $key => $item){
			$col[] = sprintf("%s='%s'", $key, $item);
		}
		return implode(", ", $col);
	}
}

class KKPaginator{

	//private
	private static $m_instance = array();
	private $m_input = null;
	private $m_total = null;		// 總筆數
	private $m_limit = null;		// 每頁顯示的筆數
	private $m_pLimit = null;		// 顯示幾頁
	private $m_numPages = null;		// 共幾頁
	private $m_current = null;		// 目前頁數
	private $m_startPage = null;	// 開始頁數
	private $m_endPage = null;		// 結束頁數
	private $m_start = 0;
	private $m_reqName = 'ppp';

	private function __construct($total=0, $limit=20, $pLimit=10){
		$this->m_input = KKInput::forge();
		$this->m_total = $total;
		$this->m_limit = $limit;
		$this->m_pLimit = $pLimit;
	}

	public static function forge($name, $total=0, $limit=20, $pLimit=10){
		if(!isset(self::$m_instance[$name])){
			self::$m_instance[$name] = new self($total, $limit, $pLimit);
		}
		return self::$m_instance[$name];
	}

	private function m_build(){
		$this->m_numPages = ceil($this->m_total/$this->m_limit);
		$this->m_current = $this->m_input->get($this->m_reqName, 1);
		if( $this->m_current < 1 ){
			$this->m_current = 1;
		}

		if( $this->m_current >= $this->m_numPages ){
			$this->m_current = $this->m_numPages;
		}

		$this->m_procStartEndPage();

		$this->m_start = $this->m_limit*($this->m_current-1);
	}

	private function m_procStartEndPage(){
		$start = floor( $this->m_current / $this->m_pLimit) ;
		$end = ceil(($this->m_current / $this->m_pLimit)) ;

		if($start == $end){
			$start = $start -1;
		}

		$this->m_startPage = $start * $this->m_pLimit+1;
		$this->m_endPage = $end * $this->m_pLimit;


		if($this->m_startPage < 1){
			$this->m_startPage = 1;
		}

		if($this->m_startPage > $this->m_numPages){
			$this->m_startPage = floor($this->m_numPages / $this->m_pLimit)*$this->m_pLimit+1;
		}

		if($this->m_endPage > $this->m_numPages){
			$this->m_endPage = $this->m_numPages;
		}

		if($this->m_endPage < $this->m_startPage){
			$this->m_endPage = $this->m_startPage;
		}

	}

	public function set_page_req($req){
		$this->m_reqName = $req;
		return $this;
	}

	public function getCurrent(){
		return $this->m_current;
	}

	public function getNumPages(){
		return $this->m_numPages;
	}
	public function getTotal(){
		return $this->m_total;
	}

	public function build(){
		$this->m_build();
		return $this;
	}

	public function get_limit_sql(){
		return sprintf(" limit %s,%s ", $this->m_start, $this->m_limit);
		return $this;
	}
	
	public function getResultArray(){
		$data["total"] = $this->m_total;
		$data["limit"] = $this->m_limit;
		$data["page_limit"] = $this->m_pLimit;
		$data["num_pages"] = $this->m_numPages;
		$data["current"] = $this->m_current;
		$data["start_page"] = $this->m_startPage;
		$data["end_page"] = $this->m_endPage;
		$data["start"] = $this->m_start;
		$data["req_name"] = $this->m_reqName;
		return $data;
	}	

}
