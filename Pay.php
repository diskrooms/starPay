<?php
class pay{
	private $wxAppId = 'wx426b3015555a46be';                   //微信公众号支付 公众号appid
	private $wxAppSecret = '7813490da6f1265e4901ffb80afaa36f'; //微信公众号支付 公众号appsecret
	private $wxMchId = '1900009851';                           //微信商户平台 商户id
	private $wxMchKey = '8934e7d15453e97507ef794cf7b0519d';    //微信商户平台 商户密钥
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
	    if(!isset($code)){
	         //没有code码则跳转到微信认证页面 https://open.weixin.qq.com/connect/oauth2/authorize 要求用户进行认证 (如果只需要获取openId,则scope设为snsapi_base静默模式,不需要用户授权)
	       $request_url = urlencode($_SERVER['HTTP_URL']);
	       $authorize_url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$this->wxAppId."&redirect_uri=".$request_url."&response_type=code&scope=snsapi_base&state=STATE#wechat_redirect";
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
	       /* if(WxPayConfig::CURL_PROXY_HOST != "0.0.0.0"
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
	       return $openId;
	    }
	}
	
	/**
	 * 统一下单
	 * 调用微信统一下单接口 https://api.mch.weixin.qq.com/pay/unifiedorder
	 * @param $body            订单标题
	 * @param $out_trade_no    订单号,由商户自主生成
	 * @param $total_fee       订单总金额 单位:分
	 * @param $trade_type      交易类型  JSAPI(微信公众号支付)、NATIVE（原生扫码支付)、APP(app支付)
	 * @param $openId          交易类型为 JSAPI 时,该值必传。为微信用户在商户对应appid下的唯一标识
	 * @param $product_id      交易类型为 NATIVE 时,该值必传。此参数为二维码中包含的商品ID，商户自行定义
	 * 
	 */
	public function unifiedOrder($body,$out_trade_no,$total_fee,$trade_type='JSAPI',$openId='',$product_id='',$notify=''){
	    //校验参数
	    if(empty($body)){
	        throw new Exception("请求统一下单接口缺少参数body！");
	    }
	    if(empty($out_trade_no)){
	        throw new Exception("请求统一下单接口缺少参数out_trade_no！");
	    }
	    if(empty($total_fee)){
	        throw new Exception("请求统一下单接口缺少参数total_fee！");
	    }
	    if(($trade_type == 'JSAPI') && empty($openId)){
	        throw new Exception("交易类型为 JSAPI 时 缺少参数openId！");
	    }
	    if(($trade_type == 'NATIVE') && empty($product_id)){
	        throw new Exception("交易类型为 NATIVE 时 缺少参数product_id！");
	    }
	    empty($notify) && $notify = $this->wxNotify;
	    if(empty($notify)){
	        throw new Exception("请求统一下单接口缺少参数notify！");
	    }
	    //赋值
	    $parameters = array(
	        'body'=>$body,
	        'out_trade_no'=>$out_trade_no,
	        'total_fee'=>$total_fee,
	        'trade_type'=>$trade_type,
	        'openid'=>$openId,
	        'product_id'=>$product_id,
	        'notify_url'=>$notify,
	        
	        'appid'=>$this->wxAppId,
	        'mch_id'=>$this->wxMchId,
	        'spbill_create_ip'=>$_SERVER['REMOTE_ADDR'],
	        'nonce_str'=>self::getNonceStr()
	    );
	    $parameters['sign'] = self::sign($parameters);
	    
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
	    $result = self::postXmlCurl($xml, $wx_api_url);
	    //TODO分析返回结果
	}
	
	/**
	 * 签名算法
	 */
	private static function sign($data=array()){
	    //空的元素不参与签名
	    $data = array_filter($data);
	    ksort($data);
	    $signStr = '';
	    foreach ($data as $k=>$v){
	        $signStr .= $k.'='.$v.'&';
	    }
	    $signStr .= 'key='.$this->wxMchKey;
	    $sign = strtoupper(md5($signStr));
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
	 * @throws WxPayException
	 */
	private static function postXmlCurl($xml, $url, $useCert = false, $second = 30)
	{		
		$ch = curl_init();
		//设置超时
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);
		
		//如果有配置代理这里就设置代理
		if(WxPayConfig::CURL_PROXY_HOST != "0.0.0.0" 
			&& WxPayConfig::CURL_PROXY_PORT != 0){
			curl_setopt($ch,CURLOPT_PROXY, WxPayConfig::CURL_PROXY_HOST);
			curl_setopt($ch,CURLOPT_PROXYPORT, WxPayConfig::CURL_PROXY_PORT);
		}
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
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
		//运行curl
		$data = curl_exec($ch);
		//返回结果
		if($data){
			curl_close($ch);
			return $data;
		} else { 
			$error = curl_errno($ch);
			curl_close($ch);
			throw new WxPayException("curl出错，错误码:$error");
		}
	}
}

$test = new pay('wx426b3015555a46be','7813490da6f1265e4901ffb80afaa36f','1900009851','8934e7d15453e97507ef794cf7b0519d','http://xxx.com/');
$test->unifiedOrder('test');