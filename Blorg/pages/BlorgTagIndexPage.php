<?php

require_once 'SwatDB/SwatDBClassMap.php';
require_once 'SwatI18N/SwatI18NLocale.php';
require_once 'Site/pages/SitePageDecorator.php';
require_once 'Site/exceptions/SiteNotFoundException.php';
require_once 'Blorg/Blorg.php';

/**
 * Displays an index of all tags with post counts
 *
 * @package   Blörg
 * @copyright 2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class BlorgTagIndexPage extends SitePageDecorator
{
	// {{{ protected properties

	protected $tags;

	// }}}

	// init phase
	// {{{ public function init()

	public function init()
	{
		parent::init();
		$this->initTags();
	}

	// }}}
	// {{{ protected function initTags()

	protected function initTags()
	{
		$instance_id = $this->app->getInstanceId();

		$sql = sprintf('select BlorgTag.title, BlorgTag.shortname,
					count(BlorgPost.id) as post_count
				from BlorgTag
					inner join BlorgPostTagBinding on
						BlorgTag.id = BlorgPostTagBinding.tag
					inner join BlorgPost on
						BlorgPostTagBinding.post = BlorgPost.id
					inner join Instance on BlorgTag.instance = Instance.id
				where BlorgTag.instance %s %s and BlorgPost.enabled = %s
					group by BlorgTag.title, BlorgTag.shortname
					order by BlorgTag.title',
			SwatDB::equalityOperator($instance_id),
			$this->app->db->quote($instance_id, 'integer'),
			$this->app->db->quote(true, 'boolean'));

		$this->tags = SwatDB::query($this->app->db, $sql);
	}

	// }}}

	// build phase
	// {{{ protected function buildContent()

	protected function buildContent()
	{
		$this->layout->startCapture('content');
		Blorg::displayAd($this->app, 'top');
		$this->displayTags();
		Blorg::displayAd($this->app, 'bottom');
		$this->layout->endCapture();
	}

	// }}}
	// {{{ protected function buildNavBar()

	protected function buildNavBar()
	{
		$path = $this->app->config->blorg->path.'tag';
		$this->layout->navbar->createEntry(Blorg::_('Tags'), $path);
	}

	// }}}
	// {{{ protected function buildTitle()

	protected function buildTitle()
	{
		$this->layout->data->title = Blorg::_('Tags');
	}

	// }}}
	// {{{ protected function displayTags()

	protected function displayTags()
	{
		$path = $this->app->config->blorg->path.'tag';
		$locale = SwatI18NLocale::get();

		$ul_tag = new SwatHtmLTag('ul');
		$ul_tag->class = 'blorg-archive-tags';
		$ul_tag->open();
		foreach ($this->tags as $tag) {
			$li_tag = new SwatHtmlTag('li');
			$li_tag->open();
			$anchor_tag = new SwatHtmlTag('a');
			$anchor_tag->href = sprintf('%s/%s', $path, $tag->shortname);
			$anchor_tag->setContent($tag->title);

			$post_count_span = new SwatHtmlTag('span');
			$post_count_span->setContent(sprintf(
				Blorg::ngettext(' (%s post)', ' (%s posts)',
				$tag->post_count),
				$locale->formatNumber($tag->post_count)));

			$heading_tag = new SwatHtmlTag('h4');
			$heading_tag->class = 'blorg-archive-tag-title';
			$heading_tag->open();

			$anchor_tag->display();
			$post_count_span->display();
			$heading_tag->close();
			$li_tag->close();
		}
		$ul_tag->close();
	}

	// }}}
}

?>
