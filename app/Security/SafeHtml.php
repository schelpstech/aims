<?php

declare(strict_types=1);

namespace App\Security;

use DOMDocument;use DOMElement;use DOMNode;use RuntimeException;
final class SafeHtml
{
 private const ALLOWED=['p','br','strong','em','ul','ol','li','h2','h3','h4','blockquote','a'];
 public function sanitize(string$html):string{if(!class_exists(DOMDocument::class))throw new RuntimeException('Safe HTML processing is unavailable.');$html=trim($html);if($html==='')return'';$dom=new DOMDocument('1.0','UTF-8');$previous=libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD|LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($previous);$body=$dom->getElementsByTagName('body')->item(0);if(!$body)return'';$this->clean($body);$safe='';foreach(iterator_to_array($body->childNodes)as$child)$safe.=$dom->saveHTML($child);return trim($safe);}
 private function clean(DOMNode$node):void{foreach(iterator_to_array($node->childNodes)as$child){if(!$child instanceof DOMElement)continue;$tag=strtolower($child->tagName);if(in_array($tag,['script','style','iframe','object','embed','svg','math','template','form','input','button'],true)){$node->removeChild($child);continue;}if(!in_array($tag,self::ALLOWED,true)){$this->clean($child);while($child->firstChild)$node->insertBefore($child->firstChild,$child);$node->removeChild($child);continue;}foreach(iterator_to_array($child->attributes)as$attribute){if($tag!=='a'||strtolower($attribute->name)!=='href')$child->removeAttribute($attribute->name);}if($tag==='a'){$href=trim($child->getAttribute('href'));if(!$this->safeUrl($href))$child->removeAttribute('href');else$child->setAttribute('rel','noopener noreferrer');}$this->clean($child);}}
 private function safeUrl(string$url):bool{if($url==='')return false;if(str_starts_with($url,'/')&&!str_starts_with($url,'//')&&!str_contains(rawurldecode($url),'..'))return true;return filter_var($url,FILTER_VALIDATE_URL)!==false&&strtolower((string)parse_url($url,PHP_URL_SCHEME))==='https';}
}
