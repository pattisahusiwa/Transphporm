<?php
/* @description     Transformation Style Sheets - Revolutionising PHP templating    *
 * @author          Tom Butler tom@r.je                                             *
 * @copyright       2015 Tom Butler <tom@r.je> | https://r.je/                      *
 * @license         http://www.opensource.org/licenses/bsd-license.php  BSD License *
 * @version         1.0                                                             */
namespace Transphporm\Hook;
/* Handles data() and iteration() function calls from the stylesheet */
class DataFunction {
	private $dataStorage;
	private $data;
	private $baseDir;

	public function __construct(\SplObjectStorage $objectStorage, $data, $baseDir, $tss) {
		$this->dataStorage = $objectStorage;
		$this->data = $data;
		$this->baseDir = $baseDir;
		$this->tss = $tss;
	}

	public function setBaseDir($dir) {
		$this->baseDir = $dir;
	}

	/** Binds data to an element */
	public function bind(\DomNode $element, $data, $type = 'data') {
		//This is a bit of a hack to workaround #24, might need a better way of doing this if it causes a problem
		if (is_array($data) && $this->isObjectArray($data)) $data = $data[0];
		$content = isset($this->dataStorage[$element]) ? $this->dataStorage[$element] : [];
		$content[$type] = $data;
		$this->dataStorage[$element] = $content;
	}

	private function isObjectArray(array $data) {
		return count($data) === 1 && isset($data[0]) && is_object($data[0]);
	}

	public function iteration($val, $element) {
		$data = $this->getData($element, 'iteration');
		$value = $this->traverse($val, $data, $element);
		return $value;
	}

	public function key($val, $element) {
		$data = $this->getData($element, 'key');
		return $data;
	}

	/** Returns the data that has been bound to $element, or, if no data is bound to $element climb the DOM tree to find the data bound to a parent node*/
	public function getData(\DomElement $element = null, $type = 'data') {
		while ($element) {
			if (isset($this->dataStorage[$element]) && isset($this->dataStorage[$element][$type])) return $this->dataStorage[$element][$type];
			$element = $element->parentNode;
		}
		return $this->data;
	}

	public function data($val, \DomElement $element = null) {
		$data = $this->getData($element);
		$value = $this->traverse($val, $data, $element);
		return $value;
	}

	private function traverse($name, $data, $element) {
		$name[0] = str_replace(['[', ']'], ['.', ''], $name[0]);
		$parts = explode('.', $name[0]);
		$obj = $data;
		$valueParser = new \Transphporm\Parser\Value($this);

		foreach ($parts as $part) {
			if ($part === '') continue;
			$part = $valueParser->parse($part, $element)[0];
			$funcResult = $this->traverseObj($part, $obj, $valueParser, $element);

			if ($funcResult !== false) $obj = $funcResult;
			
			else $obj = $this->ifNull($obj, $part);
		}
		return $obj;
	}

	private function traverseObj($part, $obj, $valueParser, $element) {
		if (strpos($part, '(') !== false) {
			$subObjParser = new \Transphporm\Parser\Value($obj, $valueParser, false);
			return $subObjParser->parse($part, $element)[0];
		}
		else if (method_exists($obj, $part)) return call_user_func([$obj, $part]); 
		else return false;
	}

	private function ifNull($obj, $key) {
		if (is_array($obj)) return isset($obj[$key]) ? $obj[$key] : null;
		else return isset($obj->$key) ? $obj->$key : null;
	}

	public function attr($val, $element) {
		return $element->getAttribute(trim($val[0]));
	}

	private function templateSubsection($css, $doc, $element) {
		$xpathStr = (new \Transphporm\Parser\CssToXpath($css, new \Transphporm\Parser\Value($this)))->getXpath();
		$xpath = new \DomXpath($doc);
		$nodes = $xpath->query($xpathStr);
		$result = [];
		foreach ($nodes as $node) {
			$result[] = $element->ownerDocument->importNode($node, true);
		}
		return $result;
	}
	
	public function template($val, $element) {
		$newTemplate = new \Transphporm\Builder($this->baseDir . $val[0], $this->tss);
		$data = $this->getData($element);
		$doc = $newTemplate->output([], true)->body;
		if (isset($val[1])) return $this->templateSubsection($val[1], $doc, $element);
		
		$newNode = $element->ownerDocument->importNode($doc->documentElement, true);
		$result = [];
		if ($newNode->tagName === 'template') {
			foreach ($newNode->childNodes as $node) {
				$clone = $node->cloneNode(true);
				if ($clone instanceof \DomElement) $clone->setAttribute('transphporm', 'includedtemplate');
				$result[] = $clone;
			}
		}		
		//else $result[] = $newNode;
		return $result;
	}

}
