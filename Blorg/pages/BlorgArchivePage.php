<?php

/**
 * Displays an index of all years and months with posts
 *
 * @package   Blörg
 * @copyright 2008-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class BlorgArchivePage extends SitePage
{
	// {{{ protected properties

	/**
	 * Array of containing the years and months that contain posts as well
	 * as yearly and monthly post counts
	 *
	 * The array is of the form:
	 * <code>
	 * <?php
	 * array(
	 *     2007 => array(
	 *         'post_count' => 7,
	 *         'months'     => array(12 => 1, 11 => 2, 10 => 1, 9 => 3),
	 *     ),
	 *     2008 => array(
	 *         'post_count' => 3,
	 *         'months'     => array(2 => 1, 1 => 2),
	 *     ),
	 * );
	 * ?>
	 * </code>
	 *
	 * @var array
	 */
	protected $years = array();

	// }}}
	// {{{ public function __construct()

	public function __construct(
		SiteApplication $app,
		SiteLayout $layout = null,
		array $arguments = array()
	) {
		parent::__construct($app, $layout, $arguments);
		$this->inityears();
	}

	// }}}
	// {{{ public function build()

	public function build()
	{
		if (isset($this->layout->navbar))
			$this->buildNavBar();

		$this->layout->startCapture('content');
		Blorg::displayAd($this->app, 'top');
		$this->displayArchive();
		Blorg::displayAd($this->app, 'bottom');
		$this->layout->endCapture();

		$this->layout->data->title = Blorg::_('Archive');
	}

	// }}}
	// {{{ protected function buildNavBar()

	protected function buildNavBar()
	{
		$path = $this->app->config->blorg->path.'archive';
		$this->layout->navbar->createEntry(Blorg::_('Archive'), $path);
	}

	// }}}
	// {{{ protected function displayArchive()

	protected function displayArchive()
	{
		$path = $this->app->config->blorg->path.'archive';
		$locale = SwatI18NLocale::get();

		$year_ul_tag = new SwatHtmLTag('ul');
		$year_ul_tag->class = 'blorg-archive-years';
		$year_ul_tag->open();
		foreach ($this->years as $year => $values) {
			$year_li_tag = new SwatHtmlTag('li');
			$year_li_tag->open();
			$year_anchor_tag = new SwatHtmlTag('a');
			$year_anchor_tag->href = sprintf('%s/%s',
				$path,
				$year);

			$year_anchor_tag->setContent($year);

			$post_count_span = new SwatHtmlTag('span');
			$post_count_span->setContent(sprintf(
				Blorg::ngettext(' (%s post)', ' (%s posts)',
				$values['post_count']),
				$locale->formatNumber($values['post_count'])));

			$year_heading_tag = new SwatHtmlTag('h4');
			$year_heading_tag->class = 'blorg-archive-year-title';
			$year_heading_tag->open();

			$year_anchor_tag->display();
			$post_count_span->display();
			$year_heading_tag->close();

			$month_ul_tag = new SwatHtmlTag('ul');
			$month_ul_tag->open();
			foreach ($values['months'] as $month => $post_count) {
				$date = new SwatDate();

				// Set year and day so we're sure it's a valid date, otherwise
				// the month may not be set.
				$date->setDate(2010, $month, 1);

				$month_li_tag = new SwatHtmlTag('li');
				$month_li_tag->open();
				$month_anchor_tag = new SwatHtmlTag('a');
				$month_anchor_tag->href = sprintf('%s/%s/%s',
					$path,
					$year,
					BlorgPageFactory::$month_names[$month]);

				$month_anchor_tag->setContent($date->getMonthName());
				$month_anchor_tag->display();

				$post_count_span = new SwatHtmlTag('span');
				$post_count_span->setContent(sprintf(
					Blorg::ngettext(' (%s post)', ' (%s posts)', $post_count),
					$locale->formatNumber($post_count)));

				$post_count_span->display();

				$month_li_tag->close();
			}
			$month_ul_tag->close();
			$year_li_tag->close();
		}
		$year_ul_tag->close();
	}

	// }}}
	// {{{ protected function initYears()

	protected function initYears()
	{
		$this->years = false;

		if (isset($this->app->memcache)) {
			$this->years = $this->app->memcache->getNs('posts',
				'archive_years');
		}

		if ($this->years === false) {
			$this->years = array();
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

				if (!array_key_exists($year, $this->years)) {
					$this->years[$year] = array(
						'post_count' => 0,
						'months'     => array(),
					);
				}

				if (!array_key_exists($month, $this->years[$year]['months'])) {
					$this->years[$year]['months'][$month] = $row->count;
				}

				$this->years[$year]['post_count'] += $row->count;
			}

			if (isset($this->app->memcache)) {
				$this->app->memcache->setNs('posts', 'archive_years',
					$this->years);
			}
		}

		if (count($this->years) == 0) {
			throw new SiteNotFoundException('Page not found');
		}
	}

	// }}}
}

?>
