<?php
/**
 * @Author yongbaolinux
 **/
namespace Home\Controller;

use Think\Controller;
//api首页
class testTPController extends Controller {
	public function _initialize() {
		Vendor('Pay.starPay');
		$wxAppId = 'xxx';
		$wxAppSecret = 'xxx';
		$wxMchId = 'xxx';
		$wxMchKey = 'xxx';
		$this->pay = new pay($wxAppId,$wxAppSecret,$wxMchId,$wxMchKey);
	}
	
	public function testPay(){
		$openId = $this->pay->getOpenId();
		try {
			$order = time().mt_rand(10000,20000);
			
			$params = array(
				'body'=>'test',
				'out_trade_no'=>$order,
				'trade_type'=>'JSAPI',
				'total_fee'=>1,
				'openid'=>$openId,
				'notify_url'=>'http://商家的域名/testTP/testNotify'
			);
			$order = $this->pay->unifiedOrder($params);
			//print_r($order);
			$this->assign('jsPayParameters',$this->pay->getJSPayParameters($order));
			$this->assign('editAddress',json_encode(''));
			$this->display();
		} catch(Exception $e){
			print $e->getMessage();
		}
		$this->display();
	}

	//异步回调
	public function testNotify(){
		if($this->pay->notify()['checkSign'] == true){
			file_put_contents('./testNofity.txt',serialize($this->pay->notify()['notifyData']));
			//填写商家自己的业务逻辑
			$this->pay->notifySuccess();
		} else {
			$this->pay->notifyFail();
		}
	}
	
	
}