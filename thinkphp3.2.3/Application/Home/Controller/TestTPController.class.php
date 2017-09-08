<?php
/**
 * @Author yongbaolinux
 **/
namespace Home\Controller;

use Think\Controller;
//api首页
class TestTPController extends Controller {
	//初始化配置
	public function _initialize() {
		Vendor('starPay.starPay');
		$this->pay = new \pay();
	}
	
	//微信公众号JSAPI支付
	public function testJSPay(){
		$config = array(
			'appid'=>'',					//您的公众号 appid 或者应用appid
			'appsecret'=>'',				//您的公众号 appsecret
			'type'=>'wechat',				//支付类型   wechat微信支付 alipay支付宝支付
			'mchid'=>'',					//您的商户id
			'mchkey'=>''					//您的商户平台密钥
		);
		$this->pay->init($config);
		//编写商户自己的业务逻辑(接收前端参数)
		//如果商户数据库中已保存有用户的openid 可无需再调用以下函数来获取用户openid
		$openId = $this->pay->getOpenId();
		try {
			$order = time().mt_rand(10000,20000);
			
			$params = array(
				'body'=>'test',					//订单标题
				'out_trade_no'=>$order,			//商家自主订单号
				'trade_type'=>'JSAPI',			//公众号支付
				'total_fee'=>1,
				'openid'=>$openId,
				'notify_url'=>'http://商家的域名/testTP/testNotify',
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
	
	//APP支付
	public function testAppPay(){
		//编写用户自己的业务逻辑(接收前端参数)
		try {
			$order = time().mt_rand(10000,20000);
			$params = array(
				'body'=>'test',					//订单标题
				'out_trade_no'=>$order,			//自主订单号
				'trade_type'=>'APP',			//APP支付  NATIVE 原生扫码支付
				'total_fee'=>1,					//订单金额
				'notify_url'=>'http://商家的域名/testTP/testNotify',
				'spbill_create_ip'=>'127.0.0.1'
			);
			$order = $this->pay->unifiedOrder($params);
			//print_r($order);
			$appPayParameters = $this->pay->getAppPayParameters($order);
			$appPayParameters['status'] = 1;
			$appPayParameters['msg'] = '请求成功';
			echo json_encode($appPayParameters,JSON_UNESCAPED_UNICODE);
		} catch(Exception $e){
			print $e->getMessage();
		}
	}
	
	//NATIVE原生扫码支付
	public function testNativePay(){
		//编写用户自己的业务逻辑(接收前端参数)
		try {
			$order = $this->pay->createOrder();	//创建订单
			$params = array(
				'body'=>'test',					//订单标题
				'out_trade_no'=>$order,			//自主订单号
				'trade_type'=>'NATIVE',			//支付类型 原生扫码支付
				'total_fee'=>1,					//订单金额
				'product_id'=>1,				//支付类型为NATIVE时必须
				'spbill_create_ip'=>'127.0.0.1',
				'notify_url'=>'http://商家的域名/testTP/testNotify',
			);
			$order = $this->pay->unifiedOrder($params);
			$this->pay->getNativePayParameters($order);
			
		} catch(Exception $e){
			print $e->getMessage();
		}
	}

	
	//支付宝移动支付(旧版)
	public function testAlipayAppPayOld(){
		$config = array(
			'appid'=>'xxx',					//开发者应用ID	  新版接口需要
			'parterid'=>'xxx',				//支付宝合作者身份id 旧版接口需要 新版不需要
			'sellerid'=>'xxx@xxx.com',		//支付宝卖家账号
			'type'=>'alipay'				//支付类型
		);
		$this->pay->init($config);
		//编写用户自己的业务逻辑(接收前端参数)
		try {
			$order = $this->pay->createOrder();
			$params = array(
				'body'=>'',												//商品详情	 			必须
				'subject'=>'',											//商品名称	 			必须
				'total_fee'=>0.01,										//订单金额	 			必须 min 0.01元
				'notify_url'=>'',										//异步通知地址	 			必须
				'out_trade_no'=>$order,									//商户订单号		 		必须
				'private_key_path'=>getcwd().'/rsa_private_key.pem',	//用户自主生成私钥存放路径	必须 强烈建议存放在非web目录
				
				'app_id'=>'',											//客户端号    			非必须 例:external
				'appenv'=>'',											//客户端来源	 			非必须 例: appenv="system=android^version=3.0.1.2"
				'goods_type',											//商品类型	 			非必须 例：1实物交易 0虚拟交易 默认1
				'hb_fq_param'=>'',										//花呗分期参数	 			非必须	json格式 {"hb_fq_num":"3","hb_fq_seller_percent":"100"}
				'rn_check'=>'',											//是否发起实名校验 		非必须 例： T发起实名校验  F不发起实名校验
				'it_b_pay'=>'',											//未付款交易超时时间		非必须 例：30m
				'extern_token'=>'',										//授权令牌				非必须 
				'promo_params'=>'',										//商户优惠活动参数			非必须 
				'extend_params'=>''										//业务扩展参数				非必须 例:{"TRANS_MEMO":"促销"}
			);
			$alipayStr = $this->pay->aliAppPayParamsOld($params);
			echo json_encode(array('status'=>1,'str'=>$alipayStr,'out_trade_no'=>$params['out_trade_no']),JSON_UNESCAPED_UNICODE);
		} catch(Exception $e){
			print $e->getMessage();	
		}
	}
	
	//支付宝APP支付(新版)
	public function testAlipayAppPay(){
		$config = array(
			'appid'=>'xxx',					//开发者应用ID	  新版接口需要
			'parterid'=>'xxx',				//支付宝合作者身份id 旧版接口需要 新版不需要
			'sellerid'=>'xxx@xxx.com',		//支付宝卖家账号
			'type'=>'alipay'				//支付类型
		);
		$this->pay->init($config);
		//编写用户自己的业务逻辑(接收前端参数)
		try {
			$orderId = $this->pay->createOrder();
			$params = array(
				'subject'=>'',											//商品名称					必须
				'out_trade_no'=>$orderId,								//商户订单号					必须
				'total_amount'=>'',										//订单金额					必须
				'notify_url'=>'',										//异步通知地址					必须
				'private_key_path'=>getcwd().'/rsa_private_key.pem',	//用户自主生成私钥存放路径		必须 强烈建议存放在非web目录
				
				'charset'=>'',											//请求使用的编码格式			支付宝必须 但starPay缺省设置 utf-8
				'sign_type'=>'',										//签名算法					支付宝必须 但starPay会缺省设置 RSA
				'body'=>'',												//交易描述					非必须
				'timeout_express'=>'',									//交易超时时间					非必须	例:90m
				'format'=>'',											//格式						非必须	仅支持JSON
				'seller_id'=>'',										//支付宝用户ID(合作者身份id)	非必须
				'goods_type'=>'',										//商品类型					非必须	0虚拟商品	1实物商品
				'passback_params'=>'',									//回传参数 需要urlencode发送	非必须
				'promo_params'=>'',										//优惠参数					非必须
				'extend_params'=>'',									//业务扩展参数					非必须
				'enable_pay_channels'=>'',								//可用渠道					非必须
				'disable_pay_channels'=>'',								//禁用渠道					非必须
				'store_id'=>'',											//商店门店编号					非必须
				'sys_service_provider_id'=>'',							//系统商编号					非必须
				'needBuyerRealnamed'=>'',								//是否发起实名校验 			非必须 	T:发起 F:不发起
				'TRANS_MEMO'=>''										//账务备注					非必须	例:促销
			);
			$alipayStr = $this->pay->aliAppPayParams($params);
			echo json_encode(array('status'=>1,'str'=>$alipayStr,'out_trade_no'=>$params['out_trade_no']),JSON_UNESCAPED_UNICODE);
		} catch (Exception $e){
			print $e->getMessage();	
		}
	}
	
	//支付宝wap 2.0支付 (新版)
	public function testAlipayWapPay(){
		$config = array(
			'appid'=>'xxx',					//开发者应用ID	  新版接口需要
			'parterid'=>'xxx',				//支付宝合作者身份id 旧版接口需要 新版不需要
			'sellerid'=>'xxx@xxx.com',		//支付宝卖家账号
			'type'=>'alipay'				//支付类型
		);
		$this->pay->init($config);
		//编写用户自己的业务逻辑(接收前端参数)
		try {
			$orderId = $this->pay->createOrder();
			$params = array(
				'subject'=>'test',										//商品名称					必须
				'out_trade_no'=>$orderId,								//商户订单号					必须
				'total_amount'=>'1',									//订单金额 单位：分			必须
				'notify_url'=>'http://localhost',						//异步通知地址					必须
				//'private_key_path'=>getcwd().'/rsa_private_key.pem',	//用户自主生成私钥存放路	径		必须 强烈建议存放在非web目录
				'private_key_path'=>'xxx',							    //原始文本格式的私钥			必须 两种形式任选其一
				
				'charset'=>'',											//请求使用的编码格式			支付宝必须 但starPay缺省设置 utf-8
				'sign_type'=>'',										//签名算法					支付宝必须 但starPay会缺省设置 RSA
				'body'=>'test',											//交易描述					非必须
				'timeout_express'=>'',									//交易超时时间					非必须	例:90m
				'format'=>'',											//格式						非必须	仅支持JSON
				'seller_id'=>'',										//支付宝用户ID(合作者身份id)	非必须
				'goods_type'=>'',										//商品类型					非必须	0虚拟商品	1实物商品
				'passback_params'=>'',									//回传参数 需要urlencode发送	非必须
				'promo_params'=>'',										//优惠参数					非必须
				'extend_params'=>'',									//业务扩展参数					非必须
				'enable_pay_channels'=>'',								//可用渠道					非必须
				'disable_pay_channels'=>'',								//禁用渠道					非必须
				'store_id'=>'',											//商店门店编号					非必须
				'sys_service_provider_id'=>'',							//系统商编号					非必须
				'needBuyerRealnamed'=>'',								//是否发起实名校验 			非必须 	T:发起 F:不发起
				'TRANS_MEMO'=>''										//账务备注					非必须	例:促销
			);
			echo $this->pay->aliWapPayParams($params);
		} catch (Exception $e){
			
		}
	}
	
	//支付宝wap 1.0支付(旧版3.4)
	public function testAlipayWapPayOld(){
		$config = array(
			'appid'=>'xxx',					//开发者应用ID	  新版接口需要
			'parterid'=>'xxx',				//支付宝合作者身份id 旧版接口需要 新版不需要
			'sellerid'=>'xxx@xxx.com',		//支付宝卖家账号
			'type'=>'alipay'				//支付类型
		);
		$this->pay->init($config);
		//编写用户自己的业务逻辑(接收前端参数)
		try {
			$orderId = $this->pay->createOrder();
			$params = array(
				'key'=>'xxx',											//验签密钥 		签名方式为MD5时设置该值		必须
				'private_key_path'=>'xxx',								//商户私钥文件路径	签名方式为RSA时设置该值		必须
				'ali_public_key_path'=>'xxx',							//支付宝公钥文件路径 签名方式为RSA时设置该值	必须
				'total_fee'=>'0.01',									//订单金额								必须	单位:元
				'subject'=>'test',										//订单名称								必须
				'out_trade_no'=>$orderId,								//商户订单号								必须
				'notify_url'=>'xxx',									//服务器异步通知地址						必须
				'show_url'=>'xxx',										//收银台页面上 商品展示的超链接				必须
				
				'body'=>'xxx',											//商品描述								非必须
				'return_url'=>'xxx',									//服务器同步通知地址						非必须
				'sign_type'=>'',										//验签方式 支持MD5和RSA 缺省设置为RSA		非必须
				'cacert'=>'',											//ca证书路径地址，用于curl中ssl校验 请保证cacert.pem文件在当前文件夹目录中 非必须
				'transport'=>'',										//访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http 缺省设置为http 非必须
				'app_pay'=>'Y',											//启用此参数能唤起钱包APP支付宝				非必须
				'it_b_pay'=>'30m',										//未付款交易的超时时间						非必须
				'extern_token'=>'',										//钱包token								非必须
				'otherfee'=>'200',										//航旅订单其它费用 单位:元					非必须
				'airticket'=>'',										//航旅订单金额
				'rn_check'=>'',											//是否实名认证 T实名 F不实名					非必须
				'buyer_cert_no'=>'',									//买家证件号码 scene=ZJCZTJF的情况下才会校验	非必须
				'buyer_real_name'=>'',									//买家真实姓名	 scene=ZJCZTJF的情况下才会校验	非必须
				'scene'=>'',											//收单场景 如需使用该字段，需向支付宝申请开通	非必须
				'hb_fq_param'=>'',										//花呗分期参数								非必须
				'goods_type'=>'',										//商品类型 0虚拟 1实物 默认1				非必须
				'promo_params'=>'',										//商户优惠活动参数							非必须
				'enable_paymethod'=>'',									//可用渠道 用户只能在指定渠道范围内支付		非必须
				'extend_params'=>''										//业务扩展参数								非必须
				
			);
			$response = $this->pay->aliWapPayParamsOld($params);
			
		} catch (Exception $e){
			
		}
	}
	
	//支付宝wap 1.0支付(旧版3.3)
	public function testAlipayWapPayOlder(){
		$config = array(
			'appid'=>'xxx',					//开发者应用ID	  新版接口需要
			'parterid'=>'xxx',				//支付宝合作者身份id 旧版接口需要 新版不需要
			'sellerid'=>'xxx@xxx.com',		//支付宝卖家账号
			'type'=>'alipay'				//支付类型
		);
		$this->pay->init($config);
		//编写自己的前端业务逻辑
		try {
			$orderId = $this->pay->createOrder();
			$params = array(
				'key'=>'xxx',											//验签密钥 		签名方式为MD5时设置该值
				'private_key_path'=>'xxx',								//商户私钥文件路径	签名方式为RSA时设置该值
				'ali_public_key_path'=>'xxx',							//支付宝公钥文件路径 签名方式为RSA时设置该值
				'total_fee'=>'0.01',									//订单金额	必须	单位:元
				'subject'=>'test',										//订单名称	必须
				'out_trade_no'=>$orderId,								//商户订单号	必须
				'notify_url'=>'xxx',									//服务器异步通知页面路径		必须
				
				'merchant_url'=>'xx',									//用户中途退出付款返回的商户地址	非必须
				'sign_type'=>'',										//验签方式 支持MD5和RSA	缺省设置为MD5
				'input_charset'=>'',									//字符编码 支持gbk和utf-8	缺省设置为utf-8
				'cacert'=>'',											//ca证书路径地址，用于curl中ssl校验 请保证cacert.pem文件在当前文件夹目录中 非必须
				'transport'=>'',										//访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http 缺省设置为http
				'call_back_url'=>'',									//页面跳转同步通知页面路径 非必须
				'req_id'=>'',											//请求号		非必须 缺省设置为 date('Ymdhis')
				'v'=>'',												//版本号		非必须 缺省设置为 2.0
				'format'=>'',											//返回格式	非必须 缺省设置为 xml
			);
			$response = $this->pay->aliWapPayParamsOlder($params);
			//dump($response);
		} catch(Exception $e){
			
		}
	}
	
	
	//测试微信异步回调
	public function testWechatNotify(){
		if($this->pay->wechatNotify()['checkSign'] == true){
			file_put_contents('./testNofity.txt',serialize($this->pay->notify()['notifyData']));
			//填写商家自己的业务逻辑
			$this->pay->notifySuccess();
		} else {
			$this->pay->notifyFail();
		}
	}
	
	//测试alipay wap旧版3.3 异步回调
	public function testAlipayWapNotifyOlder(){
		
		$config = array(
			'partner'=>'',
			'sign_type'=>'md5',
			'private_key_path'=>'xxx',
			'ali_public_key_path'=>'xxx',
			'key'=>'xxx',
			'transport'=>'http',
			'cacert'=>'',
			'sort'=>false		//true 参数排序 同步通知   /  false固定参数 异步通知
		);
		$this->pay->alipayWapNotifyOlder($config);
	}
	
}