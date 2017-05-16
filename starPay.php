<?php
/**
 * starPay 核心库文件
 */
class pay{
	private $wxAppId = '';                                     //微信公众号支付 公众号appid
	private $wxAppSecret = '';                                 //微信公众号支付 公众号appsecret
	private $wxMchId = '';                                     //微信商户平台 商户id
	private $wxMchKey = '';                                    //微信商户平台 商户密钥
	private $wxNotify = '';                                    //微信支付回调地址
	
	private $alipayAppId = '';								   //开发者应用ID	  	  新版接口需要
	private $alipayPartner = '';							   //支付宝合作者身份id  旧版接口需要 新版不需要
	private $alipaySellerId = '';							   //支付宝卖家账号
	private $alipayNotifyUrl = '';							   //支付宝异步回调地址
	private $alipayReturnUrl = '';							   //支付宝同步回调地址
	
	private $payType = '';                                   //支付类型 wx 微信支付 alipay 支付宝支付
	private $timeout = '';                                      //curl超时时间 默认6s
	
	public function __construct($config = array()){
		if($config['type'] == 'wx'){
			$this->wxAppId = $config['appid'];
			$this->wxAppSecret = $config['appsecret'];
			$this->wxMchId = $config['mchid'];
			$this->wxMchKey = $config['mchkey'];
			$this->timeout = $config['timeout'] ? $config['timeout'] : 6;
		} else {
			$this->alipayAppId = $config['appid'] ? $config['appid'] : '';
			$this->alipayPartner = $config['parterid'] ? $config['parterid'] : '';
			$this->alipaySellerId = $config['sellerid'];
		}
	}
	
	
	/**
	 * 获取微信用户的openId
	 * 如果请求该接口的域名与微信公众平台填写的认证域名不一致,则会产生跨域的问题而无法正常获取到openid
	 * 这时就要想其他办法获取用户的openId,比如从数据库查找已经在用户登录模块保存好的openId
	 * 因为获取openId也是很多业务场景下的单独需求,所以将这个方法权限设置为public,方便以后单独调用
	 */
	public function getOpenId(){
	    //TODO 校验appId和appsecret是否设置
	    $code = addslashes(trim($_GET['code']));
	    if(!isset($code) || empty($code)){
	       //没有code码则跳转到微信认证页面 https://open.weixin.qq.com/connect/oauth2/authorize 要求用户进行认证 (如果只需要获取openId,则scope设为snsapi_base静默模式,不需要用户授权)
	       $request_url = urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].$_SERVER['QUERY_STRING']);
	       $authorize_url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$this->wxAppId."&redirect_uri=".$request_url."&response_type=code&scope=snsapi_base&state=STATE&connect_redirect=1#wechat_redirect";
	       header('Location:'.$authorize_url);
		   exit();
	    } else {
	       //如果有code码则请求微信接口 https://api.weixin.qq.com/sns/oauth2/access_token 获取openId
	       $wx_api_url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$this->wxAppId."&secret=".$this->wxAppSecret."&code=".$code."&grant_type=authorization_code";
	       $ch = curl_init();
	       //设置curl
	       curl_setopt($ch, CURLOPT_TIMEOUT, $this->time);
	       curl_setopt($ch, CURLOPT_URL, $wx_api_url);
	       curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE); //不认证证书
	       curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
	       curl_setopt($ch, CURLOPT_HEADER, FALSE);
	       curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	       //设置代理
	       /*if(WxPayConfig::CURL_PROXY_HOST != "0.0.0.0"
	           && WxPayConfig::CURL_PROXY_PORT != 0){
	               curl_setopt($ch,CURLOPT_PROXY, WxPayConfig::CURL_PROXY_HOST);
	               curl_setopt($ch,CURLOPT_PROXYPORT, WxPayConfig::CURL_PROXY_PORT);
	         } */
	         //运行curl，结果以json形式返回
	       $res = curl_exec($ch);
	       curl_close($ch);
	         //取出openid
	       $data = json_decode($res,true);
	       $openId = $data['openid'];
		   //print_r($openId);
	       return $openId;
	    }
	}
	
	/**
	 * 统一下单
	 * 调用微信统一下单接口 https://api.mch.weixin.qq.com/pay/unifiedorder
	 * @param $payParameters 订单参数
	 * 
	 */
	public function unifiedOrder($payParameters=''){
	    //订单标题（必需）
	    $body = isset($payParameters['body']) ? $payParameters['body'] : '';
	    //外部订单号,由商户自主生成 （必需）
	    $out_trade_no = isset($payParameters['out_trade_no']) ? $payParameters['out_trade_no'] : '';
	    //订单总金额 单位:分 （必需）
	    $total_fee = isset($payParameters['total_fee']) ? $payParameters['total_fee'] : '';
	    //交易类型  JSAPI(微信公众号支付)、NATIVE（原生扫码支付)、APP(app支付) （必需）
	    $trade_type = isset($payParameters['trade_type']) ? $payParameters['trade_type'] : 'JSAPI';
	    //交易类型为 JSAPI 时,该值必传。为微信用户在商户对应appid下的唯一标识
	    $openId = isset($payParameters['openid']) ? $payParameters['openid'] : '';
	    //交易类型为 NATIVE 时,该值必传。此参数为二维码中包含的商品ID，商户自行定义   
	    $product_id = isset($payParameters['product_id']) ? $payParameters['product_id'] : '';
	    //支付完成后 微信通知回调地址（必需）
	    $notify_url = isset($payParameters['notify_url']) ? $payParameters['notify_url'] : '';
	    //随机字符串（必需）
	    $nonce_str = isset($payParameters['nonce_str']) ? $payParameters['nonce_str'] : self::getNonceStr();
	    //客户端IP（必需）
	    $spbill_create_ip = isset($payParameters['spbill_create_ip']) ? $payParameters['spbill_create_ip'] : $_SERVER['REMOTE_ADDR'];
	    
	    //设备号 门店号或者收银设备ID 
	    $device_info = isset($payParameters['device_info']) ? $payParameters['device_info'] : '';
	    //签名类型  支持HMAC-SHA256和MD5 默认MD5
	    $sign_type = isset($payParameters['sign_type']) ? $payParameters['sign_type'] : '';
	    //商品详细列表，使用Json格式，传输签名前请务必使用CDATA标签将JSON文本串保护起来
	    $detail = isset($payParameters['detail']) ? $payParameters['detail'] : '';
	    //附加数据 在查询API和支付通知中原样返回，该字段主要用于商户携带订单的自定义数据
	    $attach = isset($payParameters['attach']) ? $payParameters['attach'] : '';
	    //货币类型 默认人民币：CNY
	    $fee_type = isset($payParameters['fee_type']) ? $payParameters['fee_type'] : '';
	    //交易起始时间 订单生成时间，格式为yyyyMMddHHmmss，如2009年12月25日9点10分10秒表示为20091225091010
	    $time_start = isset($payParameters['time_start']) ? $payParameters['time_start'] : '';
	    //交易结束时间 订单失效时间，格式为yyyyMMddHHmmss，如2009年12月27日9点10分10秒表示为20091227091010 最短失效时间间隔必须大于5分钟
	    $time_expire = isset($payParameters['time_expire']) ? $payParameters['time_expire'] : '';
	    //商品标记，代金券或立减优惠功能的参数
	    $goods_tag = isset($payParameters['goods_tag']) ? $payParameters['goods_tag'] : '';
	    //指定支付方式  no_credit--指定不能使用信用卡支付
	    $limit_pay = isset($payParameters['limit_pay']) ? $payParameters['limit_pay'] : '';
	     
	    //校验参数
	    if(empty($body)){
	        throw new \Exception("请求统一下单接口缺少参数body！");
	    }
	    if(empty($out_trade_no)){
	        throw new \Exception("请求统一下单接口缺少参数out_trade_no！");
	    }
	    if(empty($total_fee)){
	        throw new \Exception("请求统一下单接口缺少参数total_fee！");
	    }
	    
	    if(($trade_type == 'JSAPI') && empty($openId)){
	        throw new \Exception("交易类型为 JSAPI 时 缺少参数openId！");
	    }
	    if(($trade_type == 'NATIVE') && empty($product_id)){
	        throw new \Exception("交易类型为 NATIVE 时 缺少参数product_id！");
	    }
	    empty($notify_url) && $notify_url = $this->wxNotify;
	    if(empty($notify_url)){
	        throw new \Exception("请求统一下单接口缺少参数notify！");
	    }
	    //赋值
	    $parameters = array(
	        'appid'=>$this->wxAppId,
	        'mch_id'=>$this->wxMchId,
	        'body'=>$body,
	        'out_trade_no'=>$out_trade_no,
	        'total_fee'=>$total_fee,
	        'trade_type'=>$trade_type,
	        'openid'=>$openId,
	        'product_id'=>$product_id,
	        'notify_url'=>$notify_url,
	        'spbill_create_ip'=>$spbill_create_ip,
	        'nonce_str'=>$nonce_str,
	        
	        'device_info'=>$device_info,
	        'sign_type'=>$sign_type,
	        'detail'=>$detail,
	        'attach'=>$attach,
	        'fee_type'=>$fee_type,
	        'time_start'=>$time_start,
	        'time_expire'=>$time_expire,
	        'goods_tag'=>$goods_tag,
	        'limit_pay'=>$limit_pay
	    );
		//print_r($parameters);
	    array_filter($parameters);
	    $parameters['sign'] = $this->sign($parameters);
	    
	    //生成xml字符串
	    $xml = '<xml>';
	    foreach ($parameters as $k=>$v){
	        if(is_array($v)){
	            $xml .= '<'.$k.'><![CDATA['.json_encode($v).']]></'.$k.'>';
	        } else {
	            $xml .= '<'.$k.'>'.$v.'</'.$k.'>';
	          }
	     }
	    $xml .= '</xml>';
	    
	    $wx_api_url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
	    $result = $this->postXmlCurl($xml, $wx_api_url);
	    //分析返回结果
		header("Content-type: application/json; charset=utf-8"); 
	    return json_encode($result,JSON_UNESCAPED_UNICODE);
	}
	
	/**
	 * 接收异步回调数据并进行验证
	 */
	private function notify(){
	    $xml = file_get_contents('php://input');   //比 $GLOBALS['HTTP_RAW_POST_DATA']占用内存更少？
	    $notifyData = $this->xml2array($xml);
	    $sign_ = $this->sign($notifyData);
	    if($sign_ == $notifyData['sign']){
	        return array('checkSign'=>true,'nofityData'=>$notifyData);
	    } else {
	        return array('checkSign'=>false,'nofityData'=>$notifyData);
	    }
	}
	
	/**
	 * 收到异步数据后向微信发送验签成功信息
	 */
	public function notifySuccess(){
	    echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
	}
	
	/**
	 * 收到异步数据后向微信发送验签失败信息
	 */
	public function notifyFail(){
	    echo '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
	}
	
	/**
	 * 签名算法
	 */
	private function sign($data=array()){
	    //空的元素不参与签名
	    $data = array_filter($data);
	    ksort($data);
	    $signStr = '';
	    foreach ($data as $k=>$v){
	        if($k != 'sign' && !is_array($v)){
	           $signStr .= $k.'='.$v.'&';
	        }
	    }
	    $signStr .= 'key='.$this->wxMchKey;
	    $sign = strtoupper(md5($signStr));
	    return $sign;
	}
	
	/**
	 * 生成少于32位的随机字符串
	 */
	private static function getNonceStr($length = 32){
	    $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
	    $str ="";
	    for ( $i = 0; $i < $length; $i++ )  {
	        $str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);
	    }
	    return $str;
	}
	
	/**
	 * 以post方式提交xml到对应的接口url
	 * 
	 * @param string $xml  需要post的xml数据
	 * @param string $url  url
	 * @param bool $useCert 是否需要证书，默认不需要
	 * @param int $second   url执行超时时间，默认30s
	 * @throws Exception
	 */
	private function postXmlCurl($xml, $url, $useCert = false, $second = 30)
	{		
		$ch = curl_init();
		//设置超时
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);
		
		//如果有配置代理这里就设置代理
		/*if(WxPayConfig::CURL_PROXY_HOST != "0.0.0.0" 
			&& WxPayConfig::CURL_PROXY_PORT != 0){
			curl_setopt($ch,CURLOPT_PROXY, WxPayConfig::CURL_PROXY_HOST);
			curl_setopt($ch,CURLOPT_PROXYPORT, WxPayConfig::CURL_PROXY_PORT);
		}*/
		curl_setopt($ch,CURLOPT_URL, $url);
		//curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
		//curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
		
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);
		
		//设置header
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		//要求结果为字符串且输出到屏幕上
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	
		if($useCert == true){
			//设置证书
			//使用证书：cert 与 key 分别属于两个.pem文件
			curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLCERT, WxPayConfig::SSLCERT_PATH);
			curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLKEY, WxPayConfig::SSLKEY_PATH);
		}
		//post提交方式
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		//echo $xml;
		//运行curl
		$data = curl_exec($ch);
		
		//返回结果
		if($data){
			curl_close($ch);
			$data = $this->xml2array($data);
			return $data;
		} else {
			$error = curl_errno($ch);
			curl_close($ch);
			throw new \Exception("curl出错，错误码:$error");
		}
	}
	
	/**
	 * xml转换成array
	 */
	private function xml2array($xml = ''){
	    //将XML转为array
	    //禁止引用外部xml实体
	    libxml_disable_entity_loader(true);
	    return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
	}
	
	/**
	 * 获取微信公众号支付所需的参数
	 * @param $order统一下单接口返回的数据
	 * @return JSAPI 支付所需的数据
	 */
	public function getJSPayParameters($order=''){
	    $order = json_decode($order,true);
	    $jsPayParameters = array();
	    $jsPayParameters['appId'] = $order['appid'];
	    $jsPayParameters['nonceStr'] = $order['nonce_str']; //或者重新生成随机字符串
	    $jsPayParameters['package'] = 'prepay_id='.$order['prepay_id'];
	    $jsPayParameters['signType'] = "MD5";
	    $jsPayParameters['timeStamp'] = time();
	    $jsPayParameters['paySign'] = $this->sign($jsPayParameters);
	    return json_encode($jsPayParameters);
	}
	
	/**
	 * 获取APP支付所需的参数
	 * @param $order 统一下单接口返回的数据
	 * @return APP 支付所需的数据
	 */
	 public function getAppPayParameters($order=''){
		 if(empty($order)){
			 throw new \Exception("参数不能为空");
		 } else {
			 $order = json_decode($order,true);
			 if($order['return_code'] == 'FAIL'){
				 throw new \Exception($order['return_msg']);
			 } else {
				 $appPayParameters = array();
				 $appPayParameters['apppid'] = $order['appid'];
				 $appPayParameters['partnerid'] = $order['mch_id'];
				 $appPayParameters['prepayid'] = $order['prepay_id'];
				 $appPayParameters['package'] = 'Sign=WXPay';
				 $appPayParameters['noncestr'] = $order['nonce_str'];	//或者重新生成随机字符串
				 $appPayParameters['timestamp'] = time();
				 $appPayParameters['sign'] = $this->sign($appPayParameters);
				 return $appPayParameters;
			 }
		 }
	 }
	 
	 /**
	  * 获取NATIVE原生扫码支付的二维码
	  */
	 public function getNativePayParameters($order=''){
		 if(empty($order)){
			 throw new \Exception("参数不能为空");
		 } else {
			 $order = json_decode($order,true);
			 if($order['return_code'] == 'FAIL'){
				 throw new \Exception($order['return_msg']);
			 } else {
				 vendor("starPay.phpqrcode.phpqrcode");
				 $text = urldecode(I('url', $order['code_url'],'trim'));
				 $size = I('size',5, 'intval');
				 QRcode::png($text,false,QR_ECLEVEL_L,$size,0);
			 }
		 }
	 }
	 
	 /**
	  * 获取物理机网卡MAC地址(不一定是真实物理网卡地址 有可能是虚拟网卡)
	  */
	private function getMac(){
	    $configs = '';
	    $match = $mac = array();
		if(PHP_OS == 'Linux'){
			exec('ifconfig',$configs);
			foreach ($configs as $config){
				preg_match('/ethernet\s*hwaddr(.*)/i', $config,$match);
				if(!empty($match)){
					preg_match('/[0-9A-Za-z:]+/', $match[1],$mac);
					return str_replace(':', '', $mac[0]);
				}
			}
		} elseif(PHP_OS == 'WINNT'){
			exec('ipconfig /all',$configs);
			foreach ($configs as $config){
				preg_match('/[0-9A-Z]{2}-[0-9A-Z]{2}-[0-9A-Z]{2}-[0-9A-Z]{2}-[0-9A-Z]{2}-[0-9A-Z]{2}/i', $config,$match);
				if(!empty($match)){
					return  str_replace('-', '', $match[0]);
				}
			}
		}
	}
	
	/**
	 * 满足分布式多进程场景的订单生成 有效避免生成重复订单号
	 */
	 public function createOrder(){
		 //获取物理机网卡MAC
		 $mac = $this->getMac();
		 //获取当前进程id
		 $pid = getmypid();
		 //时间信息
		 $dateTime = date('YmdHis',time());
		 //六位随机字符串
		 $nonceStr = self::getNonceStr(6);
		 //拼接订单号
		 return $mac.$pid.$dateTime.$nonceStr;
	 }
	 
	 /**
	  * 获取支付宝移动支付所需参数(旧版)
	  */
	 public function aliAppPayParamsOld($params=array()){
		 //校验必须参数
		 $reqParams = array('body','subject','total_fee','notify_url','out_trade_no','private_key_path');
		 foreach($reqParams as $param){
			if(empty($params[$param])){
				throw new \Exception($param.'参数不能为空');
			}
		 }
		 if(empty($this->alipayPartner) || empty($this->alipaySellerId)){
		 	throw new \Exception('旧移动支付接口合作者id和卖家账号不能为空');
		 }
		 $_params = array(
		 	'service'=>'mobile.securitypay.pay',										//接口名称			必须
			'partner'=>$this->alipayPartner,											//合作者身份id		必须
			'_input_charset'=>'UTF-8',													//编码				必须	固定为 UTF-8
			'sign_type'=>'RSA',															//签名类型			必须 暂只支持 RSA
			'notify_url'=>$params['notify_url'],										//异步通知地址	 		必须
			'out_trade_no'=>$params['out_trade_no'],									//订单号		 		必须
			'subject'=>$params['subject'],												//商品名称	 		必须
			'total_fee'=>$params['total_fee'],											//订单金额	 		必须 min 0.01元
			'body'=>$params['body'],													//商品详情	 		必须
			'payment_type'=>1,															//支付类型			必须 固定为1
			'seller_id'=>$this->alipaySellerId,											//支付宝卖家账号		必须
			
			'app_id'=>$params['app_id'] ? $params['app_id'] : '',						//客户端号    		非必须 例:external
			'appenv'=>$params['appenv'] ? $params['appenv'] : '',						//客户端来源	 		非必须 例: appenv="system=android^version=3.0.1.2"
			'goods_type'=>$params['goods_type'] ? $params['goods_type'] : '',			//商品类型	 		非必须 例：1实物交易 0虚拟交易 默认1
			'hb_fq_param'=>$params['hb_fq_param'] ? $params['hb_fq_param'] : '',		//花呗分期参数	 		非必须	json格式 {"hb_fq_num":"3","hb_fq_seller_percent":"100"}
			'rn_check'=>$params['rn_check'] ? $params['rn_check'] : '',					//是否发起实名校验 	非必须 例： T发起实名校验  F不发起实名校验
			'it_b_pay'=>$params['it_b_pay'] ? $params['it_b_pay'] : '',					//未付款交易超时时间	非必须 例：30m
			'extern_token'=>$params['extern_token'] ? $params['extern_token'] : '',		//授权令牌			非必须 
			'promo_params'=>$params['promo_params'] ? $params['promo_params'] : '',		//商户优惠活动参数		非必须 
			'extend_params'=>$params['extend_params'] ? $params['extend_params'] : ''	//业务扩展参数			非必须 例:{"TRANS_MEMO":"促销"}
		 );
		 //sign_type不参与签名
		 unset($_params['sign_type']);
		 return $this->getParamSign(array_filter($_params),$params['private_key_path']);
	 }
	 
	/**
	 * 获取支付宝APP支付所需参数(新版)
	 */
	public function aliAppPayParams($params=array()){
		//校验必须参数
		$reqParams = array('subject','total_amount','notify_url','out_trade_no','private_key_path');
		foreach($reqParams as $param){
			if(empty($params[$param])){
				throw new \Exception($param.'参数不能为空');
			}
		 }
		if(empty($this->alipayAppId)){
		 	throw new \Exception('新APP支付接口appid不能为空');
		}
		//组装请求参数
		$_params = array(
			'method'=>'alipay.trade.app.pay',																					//接口名称					必须
			'app_id'=>$this->alipayAppId,																						//开发者应用id				必须
			'charset'=>$params['charset'] ? $params['charset'] : 'utf-8',														//编码						必须 缺省utf-8
			'sign_type'=>$params['sign_type'] ? $params['sign_type'] : 'RSA2',													//签名算法					必须 缺省RSA2
			'timestamp'=>date('Y-m-d H:i:s',time()),																			//时间						必须
			'version'=>'1.0',																									//版本						必须 固定为1.0
			'notify_url'=>$params['notify_url'],																				//异步通知地址					必须
			'subject'=>$params['subject'],																						//商品标题					必须
			'out_trade_no'=>$params['out_trade_no'],																			//订单号						必须
			'total_amount'=>$params['total_amount'],																			//订单金额					必须 单位:元 min 0.01
			'product_code'=>'QUICK_MSECURITY_PAY',																				//销售产品码					必须 固定为QUICK_MSECURITY_PAY
			
			'body'=>$params['body'] ? $params['body'] : '',																		//交易描述					非必须
			'timeout_express'=>$params['timeout_express'] ? $params['timeout_express'] : '',									//交易超时时间					非必须
			'format'=>$params['format'] ? $params['format'] : '',																//格式						非必须 仅支持JSON
			'seller_id'=>$params['seller_id'] ? $params['seller_id'] : '',														//支付宝用户ID(合作者身份id)	非必须
			'goods_type'=>$params['goods_type'] ? $params['goods_type'] : '',													//商品类型					非必须	0虚拟商品	1实物商品
			'passback_params'=>$params['passback_params'] ? $params['passback_params'] : '',									//回传参数 需要urlencode发送	非必须
			'promo_params'=>$params['promo_params'] ? $params['promo_params'] : '',												//优惠参数					非必须
			'extend_params'=>$params['extend_params'] ? $params['extend_params'] : '',											//业务扩展参数					非必须
			'enable_pay_channels'=>$params['enable_pay_channels'] ? $params['enable_pay_channels'] : '',						//可用渠道					非必须
			'disable_pay_channels'=>$params['disable_pay_channels'] ? $params['disable_pay_channels'] : '',						//禁用渠道					非必须
			'store_id'=>$params['store_id'] ? $params['store_id'] : '',															//商店门店编号					非必须
			'sys_service_provider_id'=>$params['sys_service_provider_id'] ? $params['sys_service_provider_id'] : '',			//系统商编号					非必须
			'needBuyerRealnamed'=>$params['needBuyerRealnamed'] ? $params['needBuyerRealnamed'] : '',							//是否发起实名校验 			非必须 	T:发起 F:不发起
			'TRANS_MEMO'=>$params['TRANS_MEMO'] ? $params['TRANS_MEMO'] : ''													//账务备注					非必须	例:促销
		);
		$_params = array_filter($_params);
		$_params_copy = $_params;
		//将所有业务参数组装成 biz_content
		$commonParams = array('app_id','method','format','charset','sign_type','sign','timestamp','version','notify_url','biz_content');
		foreach($_params_copy as $k=>$v){
			if(in_array($k,$commonParams)){
				unset($_params_copy[$k]);
			}
		}
		$_params['biz_content'] = json_encode($_params_copy,JSON_UNESCAPED_UNICODE);
		ksort($_params);
		return htmlspecialchars($this->getParamSign($_params,$params['private_key_path'],$_params['sign_type'],'new'));
	}
	 
	/**
	 * 支付宝wap支付2.0所需参数 (新版)
	 */
	public function aliWapPayParams($params = array()){
		header();
		//校验必须参数
		$reqParams = array('subject','total_amount','notify_url','out_trade_no','private_key_path');
		foreach($reqParams as $param){
			if(empty($params[$param])){
				throw new \Exception($param.'参数不能为空');
			}
		 }
		if(empty($this->alipayAppId)){
		 	throw new \Exception('网站支付接口appid不能为空');
		}
		//组装请求参数
		$_params = array(
			'method'=>'alipay.trade.wap.pay',																	//接口名称	必须
			'app_id'=>$this->alipayAppId,																		//开发者应用id				必须
			'charset'=>$params['charset'] ? $params['charset'] : 'utf-8',										//编码						必须 缺省utf-8
			'sign_type'=>$params['sign_type'] ? $params['sign_type'] : 'RSA',									//签名算法					必须 缺省RSA
			'timestamp'=>date('Y-m-d H:i:s',time()),															//时间						必须
			'version'=>'1.0',																					//版本						必须 固定为1.0
			'notify_url'=>$params['notify_url'],																//异步通知地址					必须										
			'subject'=>$params['subject'],																		//商品标题					必须
			'out_trade_no'=>$params['out_trade_no'],															//订单号						必须
			'total_amount'=>$params['total_amount'],															//订单金额					必须 单位:元 min 0.01
			'product_code'=>'QUICK_MSECURITY_PAY',																//销售产品码					必须 固定为QUICK_MSECURITY_PAY
			
			'format'=>$params['format'] ? $params['format'] : '',												//格式						非必须 仅支持JSON
			'return_url'=>$params['return_url'] ? $params['return_url'] : '',									//同步通知地址					非必须
			'body'=>$params['body'] ? $params['body'] : '',														//交易描述					非必须
			'timeout_express'=>$params['timeout_express'] ? $params['timeout_express'] : '',					//交易超时时间					非必须
			'seller_id'=>$params['seller_id'] ? $params['seller_id'] : '',										//支付宝用户ID(合作者身份id)	非必须
			'auth_token'=>$params['auth_token'] ? $params['auth_token'] : '',									//针对用户授权接口
			'goods_type'=>$params['goods_type'] ? $params['goods_type'] : '',									//商品类型					非必须	0虚拟商品	1实物商品
			'passback_params'=>$params['passback_params'] ? $params['passback_params'] : '',					//回传参数 需要urlencode发送	非必须
			'promo_params'=>$params['promo_params'] ? $params['promo_params'] : '',								//优惠参数					非必须
			'extend_params'=>$params['extend_params'] ? $params['extend_params'] : '',							//业务扩展参数					非必须
			'enable_pay_channels'=>$params['enable_pay_channels'] ? $params['enable_pay_channels'] : '',		//可用渠道					非必须
			'disable_pay_channels'=>$params['disable_pay_channels'] ? $params['disable_pay_channels'] : '',		//禁用渠道					非必须
			'store_id'=>$params['store_id'] ? $params['store_id'] : '',											//商店门店编号					非必须
			'sys_service_provider_id'=>$params['sys_service_provider_id'] ? $params['sys_service_provider_id'] : '',	//系统商编号					非必须
			'needBuyerRealnamed'=>$params['needBuyerRealnamed'] ? $params['needBuyerRealnamed'] : '',			//是否发起实名校验 			非必须 	T:发起 F:不发起
			'TRANS_MEMO'=>$params['TRANS_MEMO'] ? $params['TRANS_MEMO'] : ''									//账务备注					非必须	例:促销
		);
		$_params = array_filter($_params);
		$_params_copy = $_params;
		//将所有业务参数组装成 biz_content
		$commonParams = array('app_id','method','format','charset','sign_type','sign','timestamp','version','notify_url','biz_content');
		foreach($_params_copy as $k=>$v){
			if(in_array($k,$commonParams)){
				unset($_params_copy[$k]);
			}
		}
		$_params['biz_content'] = json_encode($_params_copy,JSON_UNESCAPED_UNICODE);
		ksort($_params);
		return $this->buildForm($this->getParamSign($_params,$params['private_key_path'],$_params['sign_type'],'new',false));
		
	}
	
	/**
	 * 支付宝参数签名(支持旧版)
	 */
	private function getParamSign($params = array(),$private_key_path,$sign_type = 'RSA',$ver = 'old',$return = true){
		$temp = "";
		foreach ($params as $k => $v){
			$temp .= $k . '=' . $v . '&';
		}
		//echo $temp;
		$temp = substr($temp, 0, strlen($temp)-1);
		if(get_magic_quotes_gpc()){
			$temp = stripslashes($temp);
		}
		
		$sign = $this->rsaSign($temp,$private_key_path,$sign_type);
		
		if($return){
			if($ver == 'old'){
				return $temp.'&sign='.urlencode($sign).'&sign_type="RSA"';
			} else {
				return $temp.'&sign='.urlencode($sign);
			}
		} else {
			$params['sign'] = $sign;
			return $params;
		}
	}
	
	/**
	 * 支付宝wap支付组装Form表单 (POST提交)
	 */
	 private function buildForm($params){
		
		$sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='https://openapi.alipay.com/gateway.do?charset=utf-8' method='POST'>";
		while (list ($key, $val) = each ($params)) {
			if (!empty($val)) {
				//$val = $this->characet($val, $this->postCharset);
				$val = str_replace("'","&apos;",$val);
				//$val = str_replace("\"","&quot;",$val);
				$sHtml.= "<input type='hidden' name='".$key."' value='".$val."'/>";
			}
		}
		//submit按钮控件请不要含有name属性
        $sHtml = $sHtml."<input type='submit' value='ok' style='display:none;''></form>";
		
		$sHtml = $sHtml."<script>document.forms['alipaysubmit'].submit();</script>";
		
		return $sHtml;
	 }
	 
	/**
	 * 支付宝RSA签名(支持旧版)
	 */
	private function rsaSign($data, $private_key_path,$sign_type) {
		if(!file_exists($private_key_path)){
			$res = "-----BEGIN RSA PRIVATE KEY-----\n" .
					wordwrap($private_key_path, 64, "\n", true) .
					"\n-----END RSA PRIVATE KEY-----";
		} else {
			$priKey = file_get_contents($private_key_path);
			$res = openssl_get_privatekey($priKey);
		}
		if(!$res){
			exit('您使用的私钥格式错误，请检查RSA私钥配置'); 
		}
		if($sign_type == 'RSA'){
			openssl_sign($data, $sign, $res);
		} elseif($sign_type == 'RSA2'){
			openssl_sign($data, $sign, $res, OPENSSL_ALGO_SHA256);
		}
		openssl_free_key($res);
		//base64编码
		$sign = base64_encode($sign);
		return $sign;
	}
}
