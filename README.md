# pay
----------------------------
***********概要 ***********
----------------------------
starPay是一个聚合了微信支付和支付宝支付的极轻量支付类库
全库的核心只有一个文件(开启微信的二维码支付则还需要引入一个二维码生成库)
starPay在移动互联网的大潮下应运而生。
从实际应用场景为出发点，力求打造一个方便快捷轻巧实用的支付库。
让千千万万的程序员以及互联网创业者的双手从键盘上解放出来
以便让所有人有更多的精力和时间投入到业务逻辑的思考和实践中去

目前已经接入的有：

微信
    微信公众号支付 \ 微信APP支付 \ 微信二维码支付
    
支付宝
    支付宝wap支付(新旧两个版本均支持) \ 支付宝APP支付(新旧两个版本均支持)
    
后续会根据实际需要逐渐增加支付功能，如果你在项目中有其他的支付需要，欢迎随时联系我。

目前已支持的框架：
    ThinkPHP3.2.3

后续会增加对其他PHP框架的支持，如果你有其他框架或者不同框架版本的需求，欢迎随时联系我。
根目录下的 starPay.php 是类库核心文件
thinkphp3.2.3 文件夹是已经将 starPay 嵌入到其Vendor库目录下的 thinkphp 框架完整代码

--------------------------------
**********文档*****************
--------------------------------
<code>
//微信公众号JSAPI支付
public function testJSPay(){
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
</code>
