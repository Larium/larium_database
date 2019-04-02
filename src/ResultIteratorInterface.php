<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace Larium\Database;

/**
 * Description of ResultIteratorInterface 
 *
 * An interface to allow iterate through the result set.
 * With this approach there is no need to pre-fetch the result set to an array.
 * Each row of result set is fetched on iteration so there is no need to build 
 * large datasets in memory.
 *
 * @package Database
 * @author  Andreas Kollaros
 * @license MIT {@link http://opensource.org/licenses/mit-license.php}
 */
interface ResultIteratorInterface extends \Iterator, \Countable
{

}
