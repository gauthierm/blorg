<?php

/**
 * Displays a link to the weblog archive
 *
 * - <kbd>boolean display_full</kbd> if true, a expanded list of years with
 *                                   post counts is displayed. The years link
 *                                   to year archive pages. True by default.
 *
 * @package   Blörg
 * @copyright 2008-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class BlorgArchiveGadget extends SiteGadget
{
	// {{{ protected function displayContent()

	protected function displayContent()
	{
		if ($this->getValue('display_full')) {
			$this->displayArchive();
		}
	}

	// }}}
	// {{{ protected function define()

	protected function define()
	{
		$this->defineDefaultTitle(Blorg::_('Archive'));
		$this->defineSetting('display_full', Blorg::_('Display Full Archive'),
			'boolean', true);

		$this->defineDescription(Blorg::_(
			'Links to the archive page and provides a summarized list of '.
			'posts organized by the posts’ publish dates.'));
	}

	// }}}
	// {{{ protected function displayTitle()

	/**
	 * Displays the title of title of this widget with a link to the archive.
	 *
	 * The title is displayed in a h3 element with the CSS class
	 * 'site-gadget-title'.
	 */
	protected function displayTitle()
	{
		$path = $this->app->config->blorg->path.'archive';

		$header = new SwatHtmlTag('h3');
		$header->class = 'site-gadget-title';

		$link = new SwatHtmlTag('a');
		$link->setContent($this->getTitle());
		$link->href = $path;

		$header->open();
		$link->display();
		$header->close();
	}

	// }}}
	// {{{ protected function displayArchive()

	protected function displayArchive()
	{
		$years = $this->getYears();
		if (count($years) === 0)
			return;

		$current_year = date('Y');

		$path = $this->app->config->blorg->path.'archive';
		$locale = SwatI18NLocale::get();

		$year_ul_tag = new SwatHtmLTag('ul');
		$year_ul_tag->class = 'blorg-archive-years';
		$year_ul_tag->open();
		foreach ($years as $year => $values) {
			$year_li_tag = new SwatHtmlTag('li');
			$year_li_tag->open();
			$year_anchor_tag = new SwatHtmlTag('a');
			$year_anchor_tag->href = sprintf('%s/%s',
				$path,
				$year);

			$year_anchor_tag->setContent($year);
			$year_anchor_tag->display();

			$post_count_span = new SwatHtmlTag('span');
			$post_count_span->setContent(sprintf(
				Blorg::ngettext(' (%s post)', ' (%s posts)',
				$values['post_count']),
				$locale->formatNumber($values['post_count'])));

			$post_count_span->display();

			// show month links for current year
			if ($year == $current_year) {

				$month_ul_tag = new SwatHtmlTag('ul');
				$month_ul_tag->open();
				foreach ($values['months'] as $month => $post_count) {
					$date = new SwatDate();
					$date->setMonth($month);

					$month_li_tag = new SwatHtmlTag('li');
					$month_li_tag->open();
					$month_anchor_tag = new SwatHtmlTag('a');
					$month_anchor_tag->href = sprintf('%s/%s/%s',
						$path,
						$year,
						BlorgPageFactory::$month_names[$month]);

					$month_anchor_tag->setContent(
						$date->formatLikeIntl('MMMM'));

					$month_anchor_tag->display();

					$post_count_span = new SwatHtmlTag('span');
					$post_count_span->setContent(sprintf(
						Blorg::ngettext(' (%s post)', ' (%s posts)',
						$post_count),
						$locale->formatNumber($post_count)));

					$post_count_span->display();

					$month_li_tag->close();
				}
				$month_ul_tag->close();
			}

			$year_li_tag->close();
		}
		$year_ul_tag->close();
	}

	// }}}
	// {{{ protected function getYears()

	protected function getYears()
	{
		$years = false;

		if (isset($this->app->memcache)) {
			$years = $this->app->memcache->getNs('posts', 'archive_gadget');
		}

		if ($years === false) {
			$years = array();

			$instance_id = $this->app->getInstanceId();

			$sql = sprintf('select count(id) as count,
					extract(year from convertTZ(publish_date, %s)) as year,
					extract(month from convertTZ(publish_date, %s)) as month
				from BlorgPost
				where instance %s %s and enabled = %s
				group by year, month
				order by year desc, month desc',
				$this->app->db->quote(
					$this->app->default_time_zone->getName(), 'text'),
				$this->app->db->quote(
					$this->app->default_time_zone->getName(), 'text'),
				SwatDB::equalityOperator($instance_id),
				$this->app->db->quote($instance_id, 'integer'),
				$this->app->db->quote(true, 'boolean'));

			$rs = SwatDB::query($this->app->db, $sql, null,
				array('integer', 'integer', 'integer'));

			while ($row = $rs->fetchRow(MDB2_FETCHMODE_OBJECT)) {
				$year  = $row->year;
				$month = $row->month;

				if (!array_key_exists($year, $years)) {
					$years[$year] = array(
						'post_count' => 0,
						'months'     => array(),
					);
				}

				if (!array_key_exists($month, $years[$year]['months'])) {
					$years[$year]['months'][$month] = $row->count;
				}

				$years[$year]['post_count'] += $row->count;
			}

			if (isset($this->app->memcache)) {
				$this->app->memcache->setNs('posts', 'archive_gadget', $years);
			}
		}

		return $years;
	}

	// }}}
}

?>
