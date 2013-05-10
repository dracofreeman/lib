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
		$this->m_start = $this->m_start < 0 ? 0 : $this->m_start; 
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

class KKForm{
	private static $m_instanceArray = array();
	
	private $m_id = null;
	private $m_name = null;
	private $m_method = "POST";
	private $m_action = "";
	private $m_elArray = array();
	public $els = array();
	
	private function __construct($name){
		$this->m_id = $name;
		$this->m_name = $name;
	}
	
	public static function forge($name="default"){
		if( !isset(self::$m_instanceArray[$name]) ){
			self::$m_instanceArray[$name] = new self($name);
		}
		return self::$m_instanceArray[$name];
	}
	
	public function setID($value=null){
		if(isset($value) && !empty($value)){
			$this->m_id = trim($value);
		}
		return $this;
	}
	
	public function setName($value=null){
		if(isset($value) && !empty($value)){
			$this->m_name = trim($value);
		}
		return $this;
	}
	
	public function setMethod($value=null){
		if(isset($value) && !empty($value)){
			$this->m_method = trim($value);
		}
		return $this;
	}
	
	public function setAction($value=null){
		if(isset($value) && !empty($value)){
			$this->m_action = trim($value);
		}
		return $this;
	}

	public function id(){
		return $this->m_id;
	}
	
	public function name(){
		return $this->m_name;
	}

	public function method(){
		return $this->m_method;
	}
	
	public function action(){
		return $this->m_action;
	}
	
	public function start(){
		return sprintf('<form id="%s" name="%s" method="%s" action="%s">',
						$this->m_id,
						$this->m_name,
						$this->m_method,
						$this->m_action
				);
	}
	
	public function end(){
		return '</form>';
	}

	public function add( $type='Text', $id=null, $label='&nbsp;', $value=null, $params=array() ){
		if(null == $id){
			die( __FILE__ . '::' . __LINE__);
		}
		$this->m_elArray[$id] = KKFormElements::forge($type, $id, $label, $value, $params);
		return $this->m_elArray[$id];
	}	
	
	public function el($name){
		if( isset($this->m_elArray[$name]) ) {
			return $this->m_elArray[$name];
		}
		return null;
	}
	
	public function el_label($name){
		return $this->el($name)->getLabel();		
	}
	
	public function buildElements(){
		$this->els = $this->m_elArray;
		return $this;
	}
	
	public function renderHidden(){
		$out = "";
		foreach($this->m_elArray as $el){
			if($el->isHidden()){
				$out .= $el->render();
			}
		}
		return $out;
	}
}

class KKFormElements{
	public static function forge($type, $id=null, $label='&nbsp;', $value=null, $params=array()){
		$entity = sprintf('KKFormElement_%s', $type);
		return new $entity($id, $label, $value, $params);		
	}
}

abstract class KKFormElement_Abs{
	protected $_id = null;
	protected $_label = null;
	protected $_value = null;
	protected $_params = array();
	protected $_attr = array();
	protected $_class = array();
	protected $_hidden = false;
		
	public function __construct($id=null, $label='&nbsp;', $value=null, $params=array()){
		$this->_id = $id;
		$this->_label = $label;
		$this->_value = $value;
		$this->_params = $params;
	
		$this->_attr = isset($this->_params['attr']) ? $this->_params['attr'] : array();
		$this->_class = isset($this->_params['class']) ? $this->_params['class'] : array();
		$this->_init();
	}
	
	abstract function render();
	
	protected function _init(){		
	}
	
	public function setAttr($name, $value=null){
		$this->_attr[$name] = $value;
		return $this;
	}

	public function removeAttr($name){
		if( isset($this->_attr[$name]) ){
			unset($this->_attr[$name]);
		}
		return $this;
	}

	public function addClass($name){
		$cc = explode(' ', $name);
		foreach($cc as $item){
			$item = trim($item);
			if(!in_array($item, $this->_class) && !empty($item) ){
				$this->_class[] = $item;
			}
		}
		return $this;
	}	
	
	public function setParams($params=array()){
		$this->_params = $params;
		return $this; 
	}
	
	protected function _attrToString(){
		$str = '';
		foreach($this->_attr as $key => $value){
			$str .= sprintf(' %s="%s"',$key,$value);
		}
		return $str;
	}	

	protected function _classToString(){
		if(count($this->_class)){
			return 'class="' .  implode(' ',$this->_class) . '"';
		}
		return null;
	}

	public function setValue($value){
		$this->_value = $value;
		return $this;
	}

	public function isHidden(){
		return (true == $this->_hidden) ? true : false;
	}

	public function getID(){
		return isset( $this->_id ) ? $this->_id : null;
	}
	
	public function getLabel(){
		return isset( $this->_label ) ? $this->_label : null;
	}

	public function getValue(){
		return isset( $this->_value ) ? $this->_value : null;
	}

	public function getParams(){
		return isset( $this->_params ) ? $this->_params : null;
	}
	
	public function getElType(){
		$str_pre = 'KKFormElement_';
		return substr(get_class($this), strlen($str_pre));
	}	
	
	public function __toString(){
		return $this->render();
	}
	
}

class KKFormElement_Text extends KKFormElement_Abs{
	public function render(){
		$attr = $this->_attrToString();
		$class = $this->_classToString();
		return sprintf('<input type="text" id="%s" name="%s" value="%s" %s %s />',
				$this->_id,
				$this->_id,
				$this->_value,
				$attr,
				$class);
	}
}

class KKFormElement_Password extends KKFormElement_Abs{
	public function render(){
		$attr = $this->_attrToString();
		$class = $this->_classToString();
		return sprintf('<input type="password" id="%s" name="%s" value="%s" %s %s />',
				$this->_id,
				$this->_id,
				$this->_value,
				$attr,
				$class);
	}
}

class KKFormElement_Label extends KKFormElement_Abs{
	public function render(){
		$attr = $this->_attrToString();
		$class = $this->_classToString();
		return sprintf('<label id="%s" name="%s" %s %s>%s</label>',
				$this->_id,
				$this->_id,
				$attr,
				$class,
				$this->_value);
	}
}

class KKFormElement_Select extends KKFormElement_Abs{
	public function render(){
		$options = array();
		$options = $this->_params['options'];
		$attr = $this->_attrToString();
		$class = $this->_classToString();

		$result = "<select name=\"{$this->_id}\" id=\"$this->_id\" {$attr} {$class} >";
		foreach($options as $key => $value){
			if($key == $this->_value){
				$selected = "selected=\"selected\"";
			}else{
				$selected = "";
			}
			$result .= 	"<option value=\"{$key}\" {$selected} >{$value}</option>";
		}
		$result .= "</select>";

		return $result;
	}
}

class KKFormElement_Hidden extends KKFormElement_Abs{
	protected function _init(){
		$this->_hidden = true;
	}

	public function render(){
		$attr = $this->_attrToString();
		$class = $this->_classToString();
		return "<input type=\"hidden\" name=\"{$this->_id}\" id=\"{$this->_id}\" value=\"{$this->_value}\"  {$class} {$attr} />";
	}
}

class KKUri{
	private static $m_instance = array();
	private $m_url = null;
	private $m_scheme = null;
	private $m_host = null;
	private $m_path = null;
	private $m_query = null;
	private $m_queryArray = array();
	
	private function __construct($url=null){
		if(isset($url) && !empty($url)){
			$this->m_url = $url;
		}else{
			$this->m_url = $_SERVER["REQUEST_URI"];
		}
		$this->m_parse_url($this->m_url);
	}
	
	public static function forge($name="default", $url=null){
		if(!isset(self::$m_instance[$name])){
			self::$m_instance[$name] = new self($url);
		}
		return self::$m_instance[$name];
	}
	
	private function m_parse_url($url){
		$cc = parse_url($url);
		
		$this->m_scheme = isset($cc["scheme"]) ? $cc["scheme"] : null; 
		$this->m_host = isset($cc["host"]) ? $cc["host"] : null; 
		$this->m_path = isset($cc["path"]) ? $cc["path"] : null; 
		$this->m_query = isset($cc["query"]) ? $cc["query"] : null;
		
		$qq = explode("&", $cc["query"]);
		$data = array();
		foreach($qq as $item){
			list($k, $v) = explode("=", $item);
			($k) &&	($data[$k] = $v);
		}	
		$this->m_queryArray = $data;
	}
	
	public function reset(){
		$this->m_parse_url($this->m_url);
		return $this;
	}
	
	public function edit($name, $value){
		$this->m_queryArray[$name] = $value;	
		return $this;
	}
	
	public function remove($name){
		unset($this->m_queryArray[$name]);
		return $this;
	}
	
	public function reserve(){
		$args = func_get_args();
		$dataArray = array();
		foreach($this->m_queryArray as $key => $item){
			if(!in_array($key, $args)){
				continue;
			}
			$dataArray[$key] = $item;
		}
		$this->m_queryArray = $dataArray; 
		return $this;
	}
	
	public function queryString(){
		return http_build_query($this->m_queryArray);
	}
	
	public function render(){
		$args = func_get_args();

		if(in_array("?", $args)){
			return "?".$this->queryString();
		}
		
		if(!func_num_args()){
			$scheme = $this->m_scheme;
			$host = $this->m_host;
			$path = $this->m_path;
			$query = $this->queryString();
		}else{
			$scheme = in_array("scheme", $args) && $this->m_scheme ? $this->m_scheme : null;
			$host = in_array("host", $args) && $this->m_host ? $this->m_host : null;
			$path = in_array("path", $args) && $this->m_path ? $this->m_path : null;
			$query = in_array("query", $args) ? $this->queryString() : null;
		}
		
		return sprintf("%s%s%s%s%s%s",
					$scheme,
					$scheme && $host ? "://" : null,
					$host,
					$path,
					$path && $query ? "?" : null,
					$query
				);
	}
}
