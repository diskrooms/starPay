<?php
/**
 * starPay 核心库文件
 */
namespace Home\Controller;

use Think\Controller;
class pay{
	private $wxAppId = '';                                     //微信公众号支付 公众号appid
	private $wxAppSecret = '';                                 //微信公众号支付 公众号appsecret
	private $wxMchId = '';                                     //微信商户平台 商户id
	private $wxMchKey = '';                                    //微信商户平台 商户密钥
	private $wxNotify = '';                                    //微信支付回调地址
	
	private $payType = 'wx';                                   //支付类型
	private $timeout = 6;                                     //curl超时时间 默认6s
	
	public function __construct($wxAppId,$wxAppSecret,$wxMchId,$wxMchKey,$wxNotify='',$payType='wx',$timeout=6){
	    $this->wxAppId = $wxAppId;
	    $this->wxAppSecret = $wxAppSecret;
	    $this->wxMchId = $wxMchId;
	    $this->wxMchKey = $wxMchKey;
	    $this->wxNotify = $wxNotify;
	    $this->payType = $payType;
	    $this->timeout = $timeout;
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
	    empty($notify) && $notify = $this->wxNotify;
	    if(empty($notify)){
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
	    //TODO分析返回结果
	    return json_encode($result);
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
			throw new Exception("curl出错，错误码:$error");
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
	 * @param $order统一下单接口返回的订单数据
	 * @return JSAPI 支付所需的参数
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
}

