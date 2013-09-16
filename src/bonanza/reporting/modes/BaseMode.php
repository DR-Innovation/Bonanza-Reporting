<?php
namespace bonanza\reporting\modes;

abstract class BaseMode {
	
	/**
	 * The options given runtime or as environment variables.
	 * @var string[string]
	 */
	protected $_options;
	
	/**
	 * Constructs the mode
	 * @param string[string] $options
	 */
	function __construct(array $options) {
		$this->_options = $options;
		$this->sanityCheckOptions();
	}
	
	/**
	 * Start the mode.
	 */
	public abstract function start();
	
	/**
	 * Check if the options makes sense at all.
	 */
	protected abstract function sanityCheckOptions();
	
}