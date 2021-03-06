<?php
/* @description     Transformation Style Sheets - Revolutionising PHP templating    *
 * @author          Tom Butler tom@r.je                                             *
 * @copyright       2015 Tom Butler <tom@r.je> | https://r.je/                      *
 * @license         http://www.opensource.org/licenses/bsd-license.php  BSD License *
 * @version         1.0                                                             */
namespace Transphporm\Hook;
/** Hooks into the template system, gets assigned as `ul li` or similar and `run()` is called with any elements that match */
class PropertyHook implements \Transphporm\Hook {
	private $rules;
	private $origBaseDir;
	private $newBaseDir;
	private $valueParser;
	private $pseudoMatcher;
	private $properties = [];
	private $functionSet;

	public function __construct(array $rules, &$origBaseDir, $newBaseDir, PseudoMatcher $pseudoMatcher, \Transphporm\Parser\Value $valueParser, \Transphporm\FunctionSet $functionSet) {
		$this->rules = $rules;
		$this->origBaseDir = $origBaseDir;
		$this->newBaseDir = $newBaseDir;
		$this->valueParser = $valueParser;
		$this->pseudoMatcher = $pseudoMatcher;
		$this->functionSet = $functionSet;
	}

	public function run(\DomElement $element) {
		$this->functionSet->setElement($element);
		if ($this->origBaseDir !== $this->newBaseDir) $this->origBaseDir = $this->newBaseDir;
		//Don't run if there's a pseudo element like nth-child() and this element doesn't match it
		if (!$this->pseudoMatcher->matches($element)) return;

		// TODO: Have all rule values parsed before running them so that things like `content-append` are not expecting tokens
		// problem with this is that anything in data changed by run properties is not shown
		// TODO: Allow `update-frequency` to be parsed before it is accessed in rule (might need to switch location of rule check)

		foreach ($this->rules as $name => $value) {
			$result = $this->callProperty($name, $element, $this->getArgs($value, $element));
			if ($result === false) break;
		}
	}

	private function getArgs($value, $element) {
		return $this->valueParser->parseTokens($value);
	}

	public function registerProperty($name, \Transphporm\Property $property) {
		$this->properties[$name] = $property;
	}

	private function callProperty($name, $element, $value) {
		if (empty($value[0])) $value[0] = [];
		if (isset($this->properties[$name])) return $this->properties[$name]->run($value, $element, $this->rules, $this->pseudoMatcher, $this->properties);
	}
}
