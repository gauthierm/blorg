<?php

/**
 * Performs actions on files via AJAX
 *
 * @package   Blörg
 * @copyright 2008-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class BlorgPostFileAjaxServer extends SiteXMLRPCServer
{
	// {{{ public function attach()

	/**
	 * Marks a file as attached
	 *
	 * @param integer $file_id the id of the file to mark as attached.
	 *
	 * @return boolean true.
	 */
	public function attach($file_id)
	{
		$instance_id = $this->app->getInstanceId();

		$sql = sprintf('update BlorgFile set visible = %s
			where instance %s %s and id = %s',
			$this->app->db->quote(true, 'boolean'),
			SwatDB::equalityOperator($instance_id),
			$this->app->db->quote($instance_id, 'integer'),
			$this->app->db->quote($file_id, 'integer'));

		$num = SwatDB::exec($this->app->db, $sql);

		if ($num > 0 && isset($this->app->memcache)) {
			$this->app->memcache->flushNS('posts');
		}

		return true;
	}

	// }}}
	// {{{ public function detach()

	/**
	 * Marks a file as not attached
	 *
	 * @param integer $file_id the id of the file to mark as not attached.
	 *
	 * @return boolean true.
	 */
	public function detach($file_id)
	{
		$instance_id = $this->app->getInstanceId();

		$sql = sprintf('update BlorgFile set visible = %s
			where instance %s %s and id = %s',
			$this->app->db->quote(false, 'boolean'),
			SwatDB::equalityOperator($instance_id),
			$this->app->db->quote($instance_id, 'integer'),
			$this->app->db->quote($file_id, 'integer'));

		$num = SwatDB::exec($this->app->db, $sql);

		if ($num > 0 && isset($this->app->memcache)) {
			$this->app->memcache->flushNS('posts');
		}

		return true;
	}

	// }}}
	// {{{ public function delete()

	/**
	 * Deletes a file
	 *
	 * @param integer $file_id the id of the file to delete.
	 *
	 * @return boolean true.
	 */
	public function delete($file_id)
	{
		$instance_id = $this->app->getInstanceId();

		if ($this->app->getInstance() === null) {
			$path = '../../files';
		} else {
			$path = '../../files/'.$this->app->getInstance()->shortname;
		}

		$class_name = SwatDBClassMap::get('BlorgFile');
		$file = new $class_name();
		$file->setDatabase($this->app->db);
		$file->setFileBase($path);
		if ($file->load(intval($file_id))) {
			if ($file->getInternalValue('instance') === $instance_id) {
				$file->delete();
				if (isset($this->app->memcache)) {
					$this->app->memcache->flushNS('posts');
				}
			}
		}

		return true;
	}

	// }}}
}

?>
