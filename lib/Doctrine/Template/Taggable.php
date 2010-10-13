<?php
/*
 *  $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.phpdoctrine.org>.
 */

/**
 * Add tagging capabilities to your models
 *
 * @package     Doctrine
 * @subpackage  Template
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.phpdoctrine.org
 * @since       1.0
 * @version     $Revision$
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 */
class Doctrine_Template_Taggable extends Doctrine_Template
{
    protected $_options = array(
        'tagClass'      => 'TaggableTag',
        'tagField'      => 'name',
        'tagAlias'      => 'Tags',
        'className'     => '%CLASS%TaggableTag',
        'generateFiles' => false,
        'table'         => false,
        'pluginTable'   => false,
        'children'      => array()
    );

    public function __construct(array $options = array())
    {
        $this->_options = Doctrine_Lib::arrayDeepMerge($this->_options, $options);
        $this->_plugin = new Doctrine_Taggable($this->_options);
    }

    public function setUp()
    {
        $result = $this->_plugin->initialize($this->_table);

		$Table = $this->_plugin->getTable();
		if ( !$Table ) {
			$Table = $this->_table;
		}
		
        $options = array(
            'local'    => 'tag_id',
            'foreign'  => 'id',
            'refClass' => $Table->getOption('name')
        );
		
        Doctrine::getTable($this->_options['tagClass'])->bind(array($this->_table->getComponentName(), $options), Doctrine_Relation::MANY);
		
        $this->getInvoker()->hasAccessor($this->_options['tagAlias'].'String', 'getTagsString');
        $this->getInvoker()->hasAccessor($this->_options['tagAlias'].'Names', 'getTagNames');
        $this->getInvoker()->hasMutator($this->_options['tagAlias'], 'setTags');
    }

    public function getTagNames()
    {
        $tagField = $this->_options['tagField'];
        $tagNames = array();
		$alias = $this->_options['tagAlias'];
        foreach ($this->getInvoker()->$alias as $tag) {
            $tagNames[] = $tag[$tagField];
        }
        return $tagNames;
    }

    public function getTagsString($sep = ', ')
    {
        return implode($sep, $this->getTagNames());
    }

    public function setTags($tags)
    {
        $tagIds = $this->getTagIds($tags);
        $invoker = $this->getInvoker();
        $invoker->unlink($this->_options['tagAlias']);
        $invoker->link($this->_options['tagAlias'], $tagIds);
    }

    public function addTags($tags)
    {
        $tagIds = $this->getTagIds($tags);
        $invoker = $this->getInvoker();
        $invoker->link($this->_options['tagAlias'], $tagIds);
    }

    public function removeTags($tags)
    {
        $tagIds = $this->getTagIds($tags);
        $invoker = $this->getInvoker();
        $invoker->unlink($this->_options['tagAlias'], $tagIds);
    }

    public function removeAllTags()
    {
        $invoker = $this->getInvoker();
        $invoker->unlink($this->_options['tagAlias']);
    }

    public function getRelatedRecords($hydrationMode = Doctrine::HYDRATE_RECORD)
    {
        return $this->getInvoker()->getTable()
            ->createQuery('a')
            ->leftJoin('a.'.$this->_options['tagAlias'].' t')
            ->whereIn('t.id', $this->getCurrentTagIds())
            ->andWhere('a.id != ?', $this->getInvoker()->id)
            ->execute(array(), $hydrationMode);
    }

    public function getCurrentTagIds()
    {
        $tagIds = array();
		$alias = $this->_options['tagAlias'];
        foreach ($this->getInvoker()->$alias as $tag) {
            $tagIds[] = $tag['id'];
        }
        return $tagIds;
    }

    public function getTagIds($tags)
    {
        if (is_string($tags)) {
            $tagClass = $this->_options['tagClass'];
            $tagField = $this->_options['tagField'];

            $sep = strpos($tags, ',') !== false ? ',':' ';
            $tagNames = explode($sep, $tags);
            $newTagNames = array();
            foreach ($tagNames as $key => $tagName) {
                $tagName = trim($tagName);
                if ($tagName) {
                    $newTagNames[$key] = $tagName;
                }
            }

            $tagNames = array_unique($newTagNames);
            $tagsList = array();
            if ( ! empty($tagNames)) {
                $existingTags = Doctrine_Query::create()
                    ->from($tagClass.' t INDEXBY t.'.$tagField)
                    ->whereIn('t.'.$tagField, $tagNames)
                    ->fetchArray();

                foreach ($existingTags as $tag) {
                    $tagsList[] = $tag['id'];
                }

                foreach ($tagNames as $tagName) {
                    if ( ! isset($existingTags[$tagName])) {
                        $tag = new $tagClass();
                        $tag->$tagField = $tagName;
                        $tag->save();
                        $tagsList[] = $tag['id'];
                    }
                }
            }

            return $tagsList;
        } else if (is_array($tags)) {
            if (is_numeric(current($tags))) {
                return $tags;
            } else {
                return $this->getTagIds(implode(', ', $tags));
            }
        } else if ($tags instanceof Doctrine_Collection) {
            return $tags->getPrimaryKeys();
        } else {
            throw new Doctrine_Exception('Invalid $tags data provided. Must be a string of tags, an array of tag ids, or a Doctrine_Collection of tag records.');
        }
    }
}