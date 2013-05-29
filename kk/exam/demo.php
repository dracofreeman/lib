<?php 
include_once("../kklib.php");

class controller{
	
	private static $m_instance = null;
	private $m_view = null;
	private $m_uri = null;
	
	private function __construct(){
		$this->m_view = KKView::forge("view/tpl.demo.php");
		$this->m_uri = KKUri::forge();
	}
	
	public static function forge(){
		if(null == self::$m_instance){
			self::$m_instance = new self();
		}
		return self::$m_instance;
	}
	
	public function index(){
		$this->m_view->assign("link_uri", $this->m_uri->reset()->edit("method", "uri")->render("?"));
		$this->m_view->assign("link_form", $this->m_uri->reset()->edit("method", "form")->render("?"));
		echo $this->m_view;	
	}
	
	public function uri(){
		$uri = KKUri::forge("withdraw","WithdrawalStatus.php?fun=3&wid=998&b=1");
		echo $uri->edit("fun", "4")->render("?");
		echo "<br>";
		echo $uri->edit("fun", "5")->render("?");
		echo "<br>";
		echo $uri->edit("fun", "6")->render("?");
		echo "<br>";
		echo $uri->reset()->reserve("fun", "win")->render("?");
		echo "<br>";
		echo $uri->reset()->render("?");
		echo "<br>";
		echo $uri->reset()->render();
		echo "<br>";
		echo $uri->reset()->remove("fun")->render("?");
		echo "<br>";
		echo $uri->reset()->edit("uid", "a001")->render();
		echo "<br>";
		echo $uri->edit("sid", "a001")->render();
		echo sprintf('<p><a href="%s">Back</a></p>', $this->m_uri->reset()->edit("method", "index")->render("?"));
	}
	
	public function form(){
		$form = KKForm::forge();
		$data["options"] = array("PHP", "Java", "C#", "Delphi");
		$form->add("Select", "lst001", "Program : ", 2, $data);
		echo $form->start();
		echo $form->el_label("lst001");
		echo $form->el("lst001");
		echo $form->end();
		
		echo sprintf('<p><a href="%s">Back</a></p>', $this->m_uri->reset()->edit("method", "index")->render("?"));
	}
}

class front{
	private static $m_instance = null;
	private $m_input = null;
	
	private function __construct(){
		$this->m_input = KKInput::forge();
	}
	
	public static function forge(){
		if(null == self::$m_instance){
			self::$m_instance = new self();
		}
		return self::$m_instance;
	}
	
	public function run($ctrl){
		$method = $this->m_input->get("method", "index");
		
		if( false == method_exists($ctrl, $method) ){
			die("... No Action...");
		}		
		$ctrl->$method();
	}
}

front::forge()->run(controller::forge());

