# 概要
<p>starPay是一个聚合了微信支付和支付宝支付的极轻量支付类库,全库的核心只有一个文件(开启微信的二维码支付则还需要引入一个二维码生成库)。</p>
<p>starPay在移动互联网的大潮下应运而生。</p>
<p>从实际应用场景为出发点，力求打造一个方便快捷轻巧实用的支付库。把成千上万的程序员和互联网创业者从键盘上解放出来，让所有人有更多的精力和时间投入到业务逻辑的思考和实现中去。</p>

### 目前已经接入的有：

#### 微信
	微信公众号支付 \ 微信APP支付 \ 微信二维码支付
    
#### 支付宝
	支付宝wap支付(新旧两个版本均支持) \ 支付宝APP支付(新旧两个版本均支持)
后续会根据实际需要逐渐增加支付功能，如果你在项目中有其他的支付需要，欢迎随时联系我。

### 目前已支持的框架：
	ThinkPHP3.2.3

后续会增加对其他PHP框架的支持，如果你有其他框架或者不同框架版本的需求，欢迎随时联系我。
根目录下的 starPay.php 是类库核心文件
ThinkPHP3.2.3 文件夹是已经将 starPay 嵌入到其Vendor库目录下的 ThinkPHP 框架完整代码


# 文档
所有测试的样例代码都在Application\Home\Controller\TestTPController.class.php中，这里不再逐行说明。

### 微信
#### 微信支付初始化类库

	Vendor('starPay.starPay');
	$config = array(
		'appid'=>'',		//您的公众号 appid 或者应用appid
		'appsecret'=>'',	//您的公众号 appsecret
		'type'=>'wx',		//支付类型 wx微信支付 alipay支付宝支付 缺省支付宝支付
		'mchid'=>'',		//您的商户id
		'mchkey'=>''		//您的商户平台密钥
	);
	$this->pay = new \pay($config);


#### 微信支付API文档
	/*
	 * 获取微信用户的openId
	 * 如果请求该接口的域名与微信公众平台填写的认证域名不一致,则会产生跨域的问题而无法正常获取到openid
	 * 这时就要想其他办法获取用户的openId,比如从数据库查找已经在用户登录模块保存好的openId
	 * 因为获取openId也是很多业务场景下的单独需求,所以将这个方法权限设置为public,方便以后单独调用
	 * @return string 用户openid
	 */
	  $this->pay->getOpenId()

	/*
	 * 满足分布式多进程场景的订单生成 有效避免生成重复订单号
	 * @return String 商户自主生成订单号
	 */
	 $this->pay->createOrder()
 
	/*
	 * 统一下单
	 * 调用微信统一下单接口 https://api.mch.weixin.qq.com/pay/unifiedorder
	 * @param $payParameters 订单参数
	 * @return $order 	 统一下单订单数据
	 */
	  $this->pay->unifiedOrder($payParameters='')
 
	 /*
	  * 收到异步数据后向微信发送验签成功信息
	  */
	  $this->pay->notifySuccess()
  
	 /*
	  * 收到异步数据后向微信发送验签失败信息
	  */
	   $this->pay->notifyFail()
 
	 /*
	  * 获取微信公众号支付所需的参数
	  * @param $order统一下单接口返回的数据
	  * @return JSAPI 支付所需的数据，然后以对象的形式渲染到前端的 JS 函数中去，以便调起微信进行支付
	  */
	  $this->pay->getJSPayParameters($order='')
	  
	 /*
	  * 获取APP支付所需的参数
	  * @param $order 统一下单接口返回的数据
	  * @return APP 支付所需的数据
	  */
	  $this->pay->getAppPayParameters($order='')
	  
	 /*
	  * 获取NATIVE原生扫码支付的二维码
	  * @param $order 统一下单接口返回的数据
	  */
	  $this->pay->getNativePayParameters($order='')
  
 
#### 微信公众号支付
测试地址:http://域名/index.php/Home/testTP/testJSPay
<p>注意:微信公众平台申请的商户id只能用于微信公众号支付、扫二维码支付和刷卡支付，APP微信支付需要去微信开放平台单独申请商户id。
公众号支付需要微信用户的openid，如果此前用户已经进行过微信的网页登陆，那么数据库中保存有用户的openid，就无须再次拉取用户授权进行用户openid的获取。</p>

#### 微信APP支付
测试接口:http://域名/index.php/Home/testTP/testAppPay

#### 微信NATIVE原生扫码支付
测试接口:http://域名/index.php/Home/testTP/testNativePay


### 支付宝
#### 支付宝支付初始化类库
	Vendor('starPay.starPay');
	$config = array(
		'appid'=>'',					//开发者应用ID 新版接口需要
		'parterid'=>'',					//支付宝合作者身份id 旧版接口需要 新版不需要
		'sellerid'=>'',					//支付宝卖家账号
		'type'=>'alipay'				//支付类型
	);
	$this->pay = new \pay($config);				//初始化对象之后调用各API完成支付功能开发

#### 支付宝支付API文档
	/**
	 * 支付宝APP支付所需参数(新版)
	 */
	$params = array(
		'subject'=>'',						//商品名称  （必须）
		'out_trade_no'=>$orderId,				//商户订单号（必须）
		'total_amount'=>'',					//订单金额（必须）
		'notify_url'=>'',					//异步通知地址（必须）
		'private_key_path'=>getcwd().'/rsa_private_key.pem',	//用户自主生成私钥存放路径（必须） 强烈建议存放在非web目录

		'charset'=>'',						//请求使用的编码格式（支付宝必须） 但starPay缺省设置 utf-8
		'sign_type'=>'',					//签名算法（支付宝必须） starPay缺省设置 RSA 有RSA和RSA2两个选项
		'body'=>'',						//交易描述（非必须）
		'timeout_express'=>'',					//交易超时时间（非必须 例:90m）
		'format'=>'',						//格式（非必须 仅支持JSON）
		'seller_id'=>'',					//支付宝用户ID(合作者身份id)（非必须）
		'goods_type'=>'',					//商品类型（非必须 0虚拟商品 1实物商品）
		'passback_params'=>'',					//回传参数 需要urlencode发送（非必须）
		'promo_params'=>'',					//优惠参数（非必须）
		'extend_params'=>'',					//业务扩展参数（非必须）
		'enable_pay_channels'=>'',				//可用渠道（非必须）
		'disable_pay_channels'=>'',				//禁用渠道（非必须）
		'store_id'=>'',						//商店门店编号（非必须）
		'sys_service_provider_id'=>'',				//系统商编号（非必须）
		'needBuyerRealnamed'=>'',				//是否发起实名校验（非必须 T:发起 F:不发起）
		'TRANS_MEMO'=>''					//账务备注（非必须 例:促销）
	);
	echo $this->pay->aliAppPayParams($params);
	
	 /**
	 * 支付宝移动支付所需参数(旧版)
	 */
	$params = array(
		'body'=>'',			//商品详情（必须）
		'subject'=>'',			//商品名称（必须）
		'total_fee'=>0.01,		//订单金额（必须 min 0.01元）
		'notify_url'=>'',		//异步通知地址（必须）
		'out_trade_no'=>$order,		//商户订单号（必须）
		'private_key_path'=>getcwd().'/rsa_private_key.pem',	//用户自主生成私钥存放路径（必须 强烈建议存放在非web目录）

		'app_id'=>'',			//客户端号（非必须 例:external）
		'appenv'=>'',			//客户端来源（非必须 例: appenv="system=android^version=3.0.1.2"）
		'goods_type',			//商品类型（非必须 例：1实物交易 0虚拟交易 默认1）
		'hb_fq_param'=>'',		//花呗分期参数（非必须 json格式 {"hb_fq_num":"3","hb_fq_seller_percent":"100"}）
		'rn_check'=>'',			//是否发起实名校验（非必须 例： T发起实名校验  F不发起实名校验）
		'it_b_pay'=>'',			//未付款交易超时时间（非必须 例：30m）
		'extern_token'=>'',		//授权令牌（非必须）
		'promo_params'=>'',		//商户优惠活动参数（非必须）
		'extend_params'=>''		//业务扩展参数（非必须 例:{"TRANS_MEMO":"促销"}）
	);
	echo $this->pay->aliAppPayParamsOld($params);
	
	/**
	 * 支付宝wap支付所需参数(新版)
	 */
	$params = array(
		'subject'=>'test',			//商品名称（必须）
		'out_trade_no'=>$orderId,		//商户订单号（必须）
		'total_amount'=>'1',			//订单金额（必须）
		'notify_url'=>'http://localhost',	//异步通知地址（必须）
		'private_key_path'=>getcwd().'/rsa_private_key.pem',	//用户自主生成私钥存放路径（必须 强烈建议存放在非web目录）
		//'private_key_path'=>'xxx',		//原始文本格式的私钥（必须 两种形式任选其一）

		'charset'=>'',				//请求使用的编码格式（支付宝必须 但starPay缺省设置 utf-8）
		'sign_type'=>'',			//签名算法（支付宝必须 starPay缺省设置 RSA 有RSA和RSA2两个选项）
		'body'=>'test',				//交易描述（非必须）
		'timeout_express'=>'',			//交易超时时间（非必须 例:90m）
		'format'=>'',				//格式（非必须 仅支持JSON）
		'seller_id'=>'',			//支付宝用户ID(合作者身份id) （非必须）
		'goods_type'=>'',			//商品类型（非必须 0 虚拟商品 1 实物商品）
		'passback_params'=>'',			//回传参数 需要urlencode处理后发送（非必须）
		'promo_params'=>'',			//优惠参数（非必须）
		'extend_params'=>'',			//业务扩展参数（非必须）
		'enable_pay_channels'=>'',		//可用渠道（非必须）
		'disable_pay_channels'=>'',		//禁用渠道（非必须）
		'store_id'=>'',				//商店门店编号（非必须）
		'sys_service_provider_id'=>'',		//系统商编号（非必须）
		'needBuyerRealnamed'=>'',		//是否发起实名校验（非必须 T:发起 F:不发起）
		'TRANS_MEMO'=>''			//账务备注（非必须 例:促销）
	);
	echo $this->pay->aliWapPayParams($params);
	
####支付宝移动支付（旧版）
测试接口：http://域名/index.php/Home/testTP/testAlipayAppPayOld


