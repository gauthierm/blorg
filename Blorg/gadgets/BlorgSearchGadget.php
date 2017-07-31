<?php

/**
 * Displays a search box
 *
 * Available settings are:
 *
 * - <kbd>string label</kbd> - the label to use on the search box. If
 *                             unspecified, the label 'keywords' is used.
 *
 * @package   Blörg
 * @copyright 2008-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class BlorgSearchGadget extends SiteGadget
{
	// {{{ protected properties

	/**
	 * @var SiteSearchForm
	 */
	protected $form;

	// }}}
	// {{{ public function init()

	public function init()
	{
		$this->createForm();
		$this->form->init();
	}

	// }}}
	// {{{ public function getHtmlHeadEntrySet()

	public function getHtmlHeadEntrySet()
	{
		$this->createForm();
		$set = parent::getHtmlHeadEntrySet();
		$set->addEntrySet($this->form->getHtmlHeadEntrySet());
		return $set;
	}

	// }}}
	// {{{ protected function displayContent()

	protected function displayContent()
	{
		$this->createForm();
		$this->form->display();
	}

	// }}}
	// {{{ protected function define()

	protected function define()
	{
		$this->defineDefaultTitle(Blorg::_('Search'));
		$this->defineSetting('label', Blorg::_('Label'), 'string',
			Blorg::_('Keywords …'));

		$this->defineDescription(Blorg::_(
			'Displays a search form, allowing searching from any page that '.
			'displays the sidebar.'));
	}

	// }}}
	// {{{ protected function createForm()

	protected function createForm()
	{
		if ($this->form === null) {
			$base = $this->app->config->blorg->path;

			$keywords = new SwatSearchEntry();
			$keywords->id = 'keywords';

			if (isset($_GET['keywords']))
				$keywords->value = $_GET['keywords'];

			$field = new SwatFormField();
			$field->title = $this->getValue('label');
			$field->add($keywords);

			$button = new SiteUnnamedButton();
			$button->id = 'search_button';
			$button->title = 'Search';

			$this->form = new SiteSearchForm();
			$this->form->id = 'search_form';
			$this->form->action = $base.'search';
			$this->form->setMethod(SiteSearchForm::METHOD_GET);

			$field->add($button);
			$this->form->add($field);
		}
	}

	// }}}
}

?>
