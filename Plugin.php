<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 显示 Feed 订阅源聚合内容
 *
 * @package Lopwon Feed
 * @author Lopwon
 * @version 2.0.0
 * @link http://www.lopwon.com
 */
class LopwonFeed_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Lopwon_Feed')->Lopwon = array('LopwonFeed_Plugin', 'render');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){}

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {

		$links = new Typecho_Widget_Helper_Form_Element_Textarea(
		'links', NULL,
		'http://www.lopwon.com/feed/',
		_t('2. 订阅地址'),
		_t('输入 Feed 订阅源链接，每行一条（注：插件未使用缓存机制，过多的订阅会使加载耗时更长）。'));
		$form->addInput($links);

		$items = new Typecho_Widget_Helper_Form_Element_Text(
		'items', NULL,
		'3',
		_t('3. 订阅数量'),
		_t('每则 Feed 订阅源显示聚合内容标题的条数。'));
		$items->input->setAttribute('class', 'w-10');
		$form->addInput($items->addRule('isInteger',_t('请填写整数数字')));

		$mark = new Typecho_Widget_Helper_Form_Element_Text(
		'mark', NULL,
		'3',
		_t('4. 高亮阀值'),
		_t('在阀值内有更新的订阅源，则高亮名称底色（单位：天）。'));
		$mark->input->setAttribute('class', 'w-10');
		$form->addInput($mark->addRule('isInteger',_t('请填写整数数字')));

		echo '
			<b>1. 使用说明</b>（点击 <a href="http://www.lopwon.com/lopwon-feed.html" target="new" style="color:red;">这里</a> 查看插件 Lopwon Feed 使用文档）<br/>
			';

    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

    /**
     * 载入 Feed
     *
     * @access public
     * @param unknown $render
     * @return void
     */

    public static function render()
	{

        $Options = Helper::options();
        $Settings = $Options->plugin('LopwonFeed');
		$Plugin_Url = $Options->pluginUrl .'/LopwonFeed/';

		echo '<link rel="stylesheet" href="'.$Plugin_Url.'css/lopwon.feed.css" />';

		$tmp = str_replace("\r\n", "sep", $Settings->links);
		$links = array_filter(explode('sep', $tmp));

		echo '

					<div class="Lopwon_Feed">

		';

		foreach ($links as $key => $link) {

//缓存目录 - 这里注意上面建立缓存目录的路径
$cacheDir = './cache/';
//缓存名称 - 这里我采用了去除掉http之后的域名作为缓存文件名（因为也没有其他唯一值可以用了😂）
$cacheName = str_replace('/','',preg_replace('(^https?://)','',$link.'.xml.cache'));
//缓存时间 1小时 - 下面写秒
$ageInSeconds = 14400;
//清除文件状态缓存
clearstatcache();
//重新生成缓存文件的判定
//1.文件不存在时，生成
//2.当前时间-文件最后修改时间>=1小时，生成
if(!file_exists($cacheDir.$cacheName) || time() - filemtime($cacheDir.$cacheName) >= $ageInSeconds) {
  $contents = file_get_contents($link);
  file_put_contents($cacheDir.$cacheName, $contents);
}
//解析本地xml文件
$feed = simplexml_load_file($cacheDir.$cacheName);

			if ($feed) { //如果是 xml 则输出

				$feed->registerXPathNamespace('atom','http://www.w3.org/2005/Atom');

				if (!empty($feed->channel->item)) { //如果有 item 节点，则解析 Rss

					$now = strtotime("now");
					$lastRss = strtotime($feed->channel->lastBuildDate);
					$newRss = floor(($now - $lastRss)/86400);

					if ($newRss < $Settings->mark) {

						$markRss = 'mark';
						$colorRss = 'color';

					}

					echo '

							<div class="Lopwon_Feed-blog row">

								<div class="Lopwon_Feed-header">
									<a class="'.$markRss.'" href="'.$feed->channel->link.'" target="new"><div class="Lopwon_Feed-title"><h2 class="'.$colorRss.'">'.$feed->channel->title.'</h2></div></a>
									<div class="Lopwon_Feed-description">'.$feed->channel->description.'</div>
								</div>

								<div class="Lopwon_Feed-wrap"><ul>
					';

					$k = 0;

					foreach ($feed->channel->item as $item) {

						$time = date('Y-n-j', strtotime($item->pubDate));
					//	$pubDate = new DateTime($item->pubDate);
					//	$pubDate->setTimezone(new DateTimeZone('PRC'));
					//	$time = $pubDate->format('Y-m-d g:ia');

						$k++;

						if ($k < ($Settings->items) + 1) {

							echo '

									<a href="'.$item->link.'" target="new"><li class="Lopwon_Feed-item">
										<span>'.$item->title.'</span>
										<span>'.$time.'</span>
									</li></a>

							';

						}

					}

					echo '

								</ul></div>

							</div>

					';

				} else if (!empty($feed->xpath('//atom:entry'))) { //如果有 entry 节点，则解析 Atom

					$now = strtotime("now");
					$lastAtom = strtotime($feed->xpath('//atom:updated')[0][0]);
					$newAtom = floor(($now - $lastAtom)/86400);

					if ($newAtom < $Settings->mark) {

						$markAtom = 'mark';
						$colorAtom = 'color';

					}

					$linkSite = $feed->xpath('//atom:link')[1]['href']; //有些 Atom 订阅源的网站简介区域，会有多个链接且没有规范的排序，需适当调整数字，微调网站正确的链接
					$titlegSite = $feed->xpath('//atom:title')[0];
					$subtitleSite = $feed->xpath('//atom:subtitle')[0];

					echo '

							<div class="Lopwon_Feed-blog row">

								<div class="Lopwon_Feed-header">
									<a class="'.$markAtom.'" href="'.$linkSite.'" target="new"><div class="Lopwon_Feed-title"><h2 class="'.$colorAtom.'">'.$titlegSite.'</h2></div></a>
									<div class="Lopwon_Feed-description">'.$subtitleSite.'</div>
								</div>

								<div class="Lopwon_Feed-wrap"><ul>
					';

					$k = 0;

					foreach ($feed->xpath('//atom:entry') as $entry) {

						$time = date('Y-m-d g:ia', strtotime($entry->published));
					//	$published = new DateTime($entry->published);
					//	$published->setTimezone(new DateTimeZone('PRC'));
					//	$time = $published->format('Y-m-d g:ia');

						$linkEntry = $entry->link['href'];
						$titlegEntry = $entry->title;

						$k++;

						if ($k < ($Settings->items) + 1) {

							echo '

									<a href="'.$linkEntry.'" target="new"><li class="Lopwon_Feed-item">
										<span>'.$titlegEntry.'</span>
										<span>'.$time.'</span>
									</li></a>

							';

						}

					}

					echo '

								</ul></div>

							</div>

					';

				}

			} else { //如果非 xml 则输出

				echo '

						<div class="Lopwon_Feed-blog row">

							<div class="Lopwon_Feed-oops">未能与 <span>'.$link.'</span> 握手，请检查链接是否有效或订阅源内容格式是否符合标准！</div>

						</div>

				';

			}

		}

		echo '

					</div>

		';

    }

}
