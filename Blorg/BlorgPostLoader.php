<?php

/**
 * Efficient post loader
 *
 * Efficiently loads visible comment count and author subdataobjects for a
 * recordset of posts.
 *
 * Example usage:
 * <code>
 * <?php
 * $loader = new BlorgPostLoader($app->db, $app->getInstance());
 *
 * // set select fields
 * $loader->addSelectField('title');
 * $loader->addSelectField('shortname');
 * // ... etc ...
 *
 * $loader->setWhereClause('enabled = true');
 * $loader->setOrderByClause('publish_date desc');
 *
 * $posts = $loader->getPosts();
 * ?>
 * </code>
 *
 * @package   Blörg
 * @copyright 2008-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class BlorgPostLoader
{
	// {{{ protected properties

	/**
	 * @var MDB2_Driver_Common
	 */
	protected $db;

	/**
	 * @var SiteInstance
	 */
	protected $instance;

	/**
	 * @var SiteMemcacheModule
	 */
	protected $memcache;

	/**
	 * @var SwatDBRange
	 */
	protected $range;

	/**
	 * @var string
	 */
	protected $order_by_clause = 'BlorgPost.publish_date desc';

	/**
	 * @var string
	 */
	protected $where_clause;

	/**
	 * @var array
	 */
	protected $fields = array('id', 'shortname');

	/**
	 * @var boolean
	 *
	 * @see BlorgPostLoader::setLoadFiles()
	 */
	protected $load_files = false;

	/**
	 * @var boolean
	 *
	 * @see BlorgPostLoader::setLoadTags()
	 */
	protected $load_tags = false;

	// }}}
	// {{{ public function __construct()

	public function __construct(
		MDB2_Driver_Common $db,
		SiteInstance $instance = null,
		SiteMemcacheModule $memcache = null
	) {
		$this->db = $db;
		$this->instance = $instance;
		$this->memcache = $memcache;
	}

	// }}}

	// get posts
	// {{{ public function getPosts()

	public function getPosts()
	{
		$posts = false;

		if ($this->memcache !== null) {
			$key = $this->getPostsCacheKey();
			$ids = $this->memcache->getNs('posts', $key);

			if ($ids !== false) {
				$post_wrapper = SwatDBClassMap::get('BlorgPostWrapper');
				$posts = new $post_wrapper();

				if (count($ids) > 0) {
					$cached_posts = $this->memcache->getNs('posts', $ids);

					if (count($cached_posts) !== count($ids)) {
						// one or more posts are missing from the cache
						$posts = false;
					} else {
						foreach ($cached_posts as $post) {
							$posts->add($post);
						}
					}
				}

				if ($posts !== false) {
					$posts->setDatabase($this->db);
				}
			}
		}

		if ($posts === false) {
			$sql = $this->getSelectClause();
			$sql.= $this->getWhereClause();
			$sql.= $this->getOrderByClause();

			if ($this->range !== null) {
				$this->db->setLimit($this->range->getLimit(),
					$this->range->getOffset());
			}

			$post_wrapper = SwatDBClassMap::get('BlorgPostWrapper');
			$posts = SwatDB::query($this->db, $sql, $post_wrapper);

			if (in_array('author', $this->fields)) {
				$this->loadPostAuthors($posts);
			}

			if ($this->load_files) {
				$this->loadPostFiles($posts);
			}

			if ($this->load_tags) {
				$this->loadPostTags($posts);
			}

			if ($this->memcache !== null) {
				$ids = array();
				foreach ($posts as $id => $post) {
					$post_key = $key.'_'.$id;
					$ids[] = $post_key;
					$this->memcache->setNs('posts', $post_key, $post);
				}
				$this->memcache->setNs('posts', $key, $ids);
			}
		}

		return $posts;
	}

	// }}}
	// {{{ public function getPostCount()

	public function getPostCount()
	{
		$count = false;

		if ($this->memcache !== null) {
			$key = $this->getPostCountCacheKey();
			$count = $this->memcache->getNs('posts', $key);
		}

		if ($count === false) {
			$sql = 'select count(1) from BlorgPost';
			$sql.= $this->getWhereClause();
			$count = SwatDB::queryOne($this->db, $sql);

			if ($this->memcache !== null) {
				$this->memcache->setNs('posts', $key, $count);
			}
		}

		return $count;
	}

	// }}}
	// {{{ public function getPost()

	public function getPost($id)
	{
		$post = false;

		if ($this->memcache !== null) {
			$key = $this->getPostCacheKey($id);
			$post = $this->memcache->getNs('posts', $key);
		}

		if ($post === false) {
			$sql = $this->getSelectClause();
			$sql.= $this->getWhereClause();
			$sql.= sprintf(' and BlorgPost.id = %s',
				$this->db->quote($id, 'integer'));

			$this->db->setLimit(1, 0);

			$post_wrapper = SwatDBClassMap::get('BlorgPostWrapper');
			$posts = SwatDB::query($this->db, $sql, $post_wrapper);

			if (in_array('author', $this->fields)) {
				$this->loadPostAuthors($posts);
			}

			if ($this->load_files) {
				$this->loadPostFiles($posts);
			}

			if ($this->load_tags) {
				$this->loadPostTags($posts);
			}

			$post = $posts->getFirst();

			if ($this->memcache !== null) {
				$this->memcache->setNs('posts', $key, $post);
			}
		} else {
			$post->setDatabase($this->db);
		}

		return $post;
	}

	// }}}
	// {{{ public function getPostByDateAndShortname()

	public function getPostByDateAndShortname(SwatDate $date, $shortname)
	{
		$post = false;

		if ($this->memcache !== null) {
			$key = $date->formatLikeIntl('yyyyMMdd').$shortname;
			$key = $this->getPostCacheKey($key);
			$post = $this->memcache->getNs('posts', $key);
		}

		if ($post === false) {
			$sql = $this->getSelectClause();
			$sql.= $this->getWhereClause();
			$sql.= sprintf(' and BlorgPost.shortname = %s and
				date_trunc(\'month\', convertTZ(publish_date, %s)) =
					date_trunc(\'month\', timestamp %s)',
				$this->db->quote($shortname, 'text'),
				$this->db->quote($date->getTimezone()->getName(), 'text'),
				$this->db->quote($date->getDate(), 'date'));

			$this->db->setLimit(1, 0);

			$post_wrapper = SwatDBClassMap::get('BlorgPostWrapper');
			$posts = SwatDB::query($this->db, $sql, $post_wrapper);

			if (in_array('author', $this->fields)) {
				$this->loadPostAuthors($posts);
			}

			if ($this->load_files) {
				$this->loadPostFiles($posts);
			}

			if ($this->load_tags) {
				$this->loadPostTags($posts);
			}

			$post = $posts->getFirst();

			if ($this->memcache !== null) {
				$this->memcache->setNs('posts', $key, $post);
			}
		} else {
			if ($post !== null) {
				$post->setDatabase($this->db);
			}
		}

		return $post;
	}

	// }}}

	// setup methods
	// {{{ public function addSelectField()

	public function addSelectField($field)
	{
		if (!in_array($field, $this->fields)) {
			$this->fields[] = $field;
		}
	}

	// }}}
	// {{{ public function removeSelectField()

	public function removeSelectField($field)
	{
		if (in_array($field, $this->fields)) {
			$this->fields = array_diff($this->fields, array($field));
		}
	}

	// }}}
	// {{{ public function setLoadFiles()

	/**
	 * Sets whether or not to efficiently load the visible files for loaded
	 * posts
	 *
	 * Set this to true if the visible files are to be displayed, false
	 * otherwise.
	 *
	 * @param boolean $load_files
	 */
	public function setLoadFiles($load_files)
	{
		$this->load_files = (boolean)$load_files;
	}

	// }}}
	// {{{ public function setLoadTags()

	/**
	 * Sets whether or not to efficiently load the tags for loaded posts
	 *
	 * Set this to true if the tags are to be displayed, false otherwise.
	 *
	 * @param boolean $load_tags
	 */
	public function setLoadTags($load_tags)
	{
		$this->load_tags = (boolean)$load_tags;
	}

	// }}}
	// {{{ public function setOrderByClause()

	public function setOrderByClause($order_by_clause)
	{
		$this->order_by_clause = $order_by_clause;
	}

	// }}}
	// {{{ public function setWhereClause()

	public function setWhereClause($where_clause)
	{
		$this->where_clause = $where_clause;
	}

	// }}}
	// {{{ public function setRange()

	public function setRange($range = null, $offset = null)
	{
		if ($range instanceof SwatDBRange || $range === null) {
			$this->range = $range;
		} else {
			$this->range = new SwatDBRange($range, $offset);
		}
	}

	// }}}

	// helper methods
	// {{{ protected function getSelectClause()

	protected function getSelectClause()
	{
		$sql = 'select '.$this->getSelectFields().' from BlorgPost';

		if (in_array('visible_comment_count', $this->fields)) {
			$sql.= ' inner join BlorgPostVisibleCommentCountView on
				BlorgPost.id = BlorgPostVisibleCommentCountView.post and
				(BlorgPost.instance =
					BlorgPostVisibleCommentCountView.instance or
					(BlorgPost.instance is null and
						BlorgPostVisibleCommentCountView.instance is null))';
		}

		return $sql;
	}

	// }}}
	// {{{ protected function getSelectFields()

	protected function getSelectFields()
	{
		$sql = implode(', ', $this->fields);
		return $sql;
	}

	// }}}
	// {{{ protected function getWhereClause()

	protected function getWhereClause()
	{
		$instance_id = ($this->instance === null) ? null : $this->instance->id;
		$sql = sprintf(' where BlorgPost.instance %s %s',
			SwatDB::equalityOperator($instance_id),
			$this->db->quote($instance_id, 'integer'));

		if ($this->where_clause != '') {
			$sql.= ' and '.$this->where_clause;
		}

		return $sql;
	}

	// }}}
	// {{{ protected function getOrderByClause()

	protected function getOrderByClause()
	{
		$sql = '';

		if ($this->order_by_clause != '') {
			$sql.= ' order by '.$this->order_by_clause;
		}

		return $sql;
	}

	// }}}
	// {{{ protected function loadPostAuthors()

	protected function loadPostAuthors(BlorgPostWrapper $posts)
	{
		$instance_id = ($this->instance === null) ? null : $this->instance->id;

		$author_wrapper = SwatDBClassMap::get('BlorgAuthorWrapper');
		$author_sql = sprintf('select id, name, shortname, visible
			from BlorgAuthor
			where instance %s %s and id in (%%s)',
			SwatDB::equalityOperator($instance_id),
			$this->db->quote($instance_id, 'integer'));

		$posts->loadAllSubDataObjects('author', $this->db, $author_sql,
			$author_wrapper);
	}

	// }}}
	// {{{ protected function loadPostFiles()

	/**
	 * Efficiently loads visible files for a set of posts
	 *
	 * @param BlorgPostWrapper $posts the posts for which to efficiently load
	 *                                 visible files.
	 */
	protected function loadPostFiles(BlorgPostWrapper $posts)
	{
		$instance_id = ($this->instance === null) ? null : $this->instance->id;
		$wrapper = SwatDBClassMap::get('BlorgFileWrapper');

		// get post ids
		$post_ids = array();
		foreach ($posts as $post) {
			$post_ids[] = $post->id;
		}
		$post_ids = $this->db->implodeArray($post_ids, 'integer');

		// build SQL to select all visible files
		$sql = sprintf('select BlorgFile.* from BlorgFile
			where post in (%s) and visible = %s and instance %s %s
			order by post, createdate desc',
			$post_ids,
			$this->db->quote(true, 'boolean'),
			SwatDB::equalityOperator($instance_id),
			$this->db->quote($instance_id, 'integer'));

		// get all files
		$files = SwatDB::query($this->db, $sql, $wrapper);

		// assign empty recordsets for all posts
		foreach ($posts as $post) {
			$recordset = new $wrapper();
			$post->setVisibleFiles($recordset);
		}

		// assign files to correct posts
		$current_post_id = null;
		$current_recordset = null;
		foreach ($files as $file) {
			$post_id = $file->getInternalValue('post');
			if ($post_id !== $current_post_id) {
				$current_post_id = $post_id;
				$current_recordset = $posts[$post_id]->getVisibleFiles();
			}
			$current_recordset->add($file);
		}
	}

	// }}}
	// {{{ protected function loadPostTags()

	/**
	 * Efficiently loads tags for a set of posts
	 *
	 * @param BlorgPostWrapper $posts the posts for which to efficiently load
	 *                                 tags.
	 */
	protected function loadPostTags(BlorgPostWrapper $posts)
	{
		$instance_id = ($this->instance === null) ? null : $this->instance->id;
		$wrapper = SwatDBClassMap::get('BlorgTagWrapper');

		// get post ids
		$post_ids = array();
		foreach ($posts as $post) {
			$post_ids[] = $post->id;
		}
		$post_ids = $this->db->implodeArray($post_ids, 'integer');

		// build SQL to select all tags
		$sql = sprintf('select BlorgTag.*, BlorgPostTagBinding.post
			from BlorgTag
				inner join BlorgPostTagBinding on
					BlorgTag.id = BlorgPostTagBinding.tag
			where post in (%s) and BlorgTag.instance %s %s
			order by post, createdate desc',
			$post_ids,
			SwatDB::equalityOperator($instance_id),
			$this->db->quote($instance_id, 'integer'));

		// get all tags
		$tags = SwatDB::query($this->db, $sql, $wrapper);

		// assign empty recordsets for all posts
		foreach ($posts as $post) {
			$recordset = new $wrapper();
			$post->setTags($recordset);
		}

		// assign tags to correct posts
		$current_post_id = null;
		$current_recordset = null;
		foreach ($tags as $tag) {
			$post_id = $tag->getInternalValue('post');
			if ($post_id !== $current_post_id) {
				$current_post_id = $post_id;
				$current_recordset = $posts[$post_id]->getTags();
			}
			$current_recordset->add($tag);
		}
	}

	// }}}
	// {{{ protected function getPostsCacheKey()

	protected function getPostsCacheKey()
	{
		$keys = array(
			$this->range,
			$this->order_by_clause,
			$this->where_clause,
			$this->fields,
			$this->load_files,
			$this->load_tags,
		);

		return md5(serialize($keys));
	}

	// }}}
	// {{{ protected function getPostCountCacheKey()

	protected function getPostCountCacheKey()
	{
		$keys = array(
			$this->where_clause,
		);

		return md5(serialize($keys));
	}

	// }}}
	// {{{ protected function getPostCacheKey()

	protected function getPostCacheKey($id)
	{
		$keys = array(
			$id,
			$this->where_clause,
			$this->fields,
			$this->load_files,
			$this->load_tags,
		);

		return md5(serialize($keys));
	}

	// }}}
}

?>
