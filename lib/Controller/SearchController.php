<?php

/**
 * Nextcloud - nextant
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@pontapreta.net>
 * @copyright Maxence Lange 2016
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Nextant\Controller;

use \OCA\Nextant\Service\FileService;
use \OCA\Nextant\Service\BookmarkService;
use \OCA\Nextant\Service\SolrService;
use OCP\AppFramework\Controller;
use OCP\IRequest;
use OC\Files\Filesystem;
use OCP\Files\NotFoundException;
use OC\Files\View;

class SearchController extends Controller
{

    private $userId;

    private $groupManager;

    private $configService;

    private $solrService;

    private $fileService;

    private $bookmarkService;

    private $miscService;

    public function __construct($appName, IRequest $request, $userId, $groupManager, $configService, $solrService, $fileService, $bookmarkService, $miscService)
    {
        parent::__construct($appName, $request);
        
        $this->userId = $userId;
        $this->groupManager = $groupManager;
        $this->configService = $configService;
        
        $this->solrService = $solrService;
        
        $this->fileService = $fileService;
        $this->bookmarkService = $bookmarkService;
        
        $this->miscService = $miscService;
    }

    /**
     * @NoAdminRequired
     */
    public function searchRequest($query, $current_dir)
    {
        $results = array();
        
        if (! $this->solrService)
            return $results;
        
        if ($query !== null) {
            
            // $groups
            $groups = array_map(function ($value) {
                return (string) $value;
            }, array_keys($this->groupManager->getUserIdGroups($this->userId)));
            array_push($groups, '__all');
            
            $this->solrService->setOwner($this->userId, $groups);
            
            $solrResult = $this->solrService->search($query, array(
                'current_directory' => $current_dir
            ));
            
            if (! $solrResult)
                return $results;
            
            foreach ($solrResult as $item) {
                
                switch ($item->getSource()) {
                    
                    case 'files':
                        $this->fileService->initUser($this->userId, false);
                        $this->fileService->getSearchResult($item);
                        $this->fileService->endUser();
                        break;
                    
                    case 'bookmarks':
                        $this->bookmarkService->getSearchResult($item);
                        break;
                    
                    default:
                        continue;
                }
                
                if (! $item->isValid())
                    continue;
                
                $hl1 = '';
                $hl2 = '';
                if ($item->getHighlighting() !== null && is_array($item->getHighlighting())) {
                    if (sizeof($item->getHighlighting()) >= 1)
                        $hl1 = '... ' . $item->getHighlighting()[0] . ' ...';
                    if (sizeof($item->getHighlighting()) > 1)
                        $hl2 = '... ' . $item->getHighlighting()[1] . ' ...';
                    if (sizeof($item->getHighlighting()) > 2)
                        $hl1 .= '... ' . $item->getHighlighting()[2] . ' ...';
                    if (sizeof($item->getHighlighting()) > 3)
                        $hl2 .= '... ' . $item->getHighlighting()[3] . ' ...';
                }
                
                $item->setLine(1, $item->getPath());
                $item->setLine(2, $hl1);
                $item->setLine(3, $hl2);
                
                $results[] = $item->toArray();
            }
            
            // $this->miscService->log('>> ' . var_export($results, true));
        }
        
        return array(
            'config' => array(
                'index_files_nextant_only' => (($this->configService->getAppValue('index_files_nextant_only') === '1' && $this->configService->getAppValue('index_files_tree') === '1') ? 1 : 0)
            ),
            'result' => $results
        );
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function suggestRequest($query)
    {
        if (! $this->solrService)
            return false;
        
        if ($query == null || $query === '')
            return false;
        
        $ierror = null;
        $suggest = $this->solrService->suggest($query, $ierror);
        
        $err = ($ierror == null) ? 0 : $ierror->getCode();
        
        $result = array(
            'status' => $err,
            'result' => $suggest
        );
        
        // $this->miscService->log('>> ' . var_export($result, true));
        
        return $result;
    }

    /**
     * @NoCSRFRequired
     * @PublicPage
     */
    public function searchRequestPublic($query, $key)
    {
        $results = array();
        
        if ($this->configService->getAppValue('index_files_sharelink') !== '1')
            return $results;
        
        if (! $this->solrService)
            return $results;
        
        if (strpos($key, '?') > 0)
            $key = substr($key, 0, strpos($key, '?'));
        if (strpos($key, '#') > 0)
            $key = substr($key, 0, strpos($key, '#'));
        
        $share = \OC\Share\Share::getShareByToken($key);
        
        if (! $share)
            return $results;
        
        if ($query !== null) {
            
            $this->solrService->setOwner('__link_' . $share['id']);
            $solrResult = $this->solrService->search($query, array());
            
            if (! $solrResult)
                return $results;
            
            foreach ($solrResult as $item) {
                
                $item->sharedPublic(true);
                switch ($item->getSource()) {
                    
                    case 'files':
                        $this->fileService->initUser($item->getOwner(), false);
                        $this->fileService->getSearchResult($item, $share['file_target'], false);
                        $this->fileService->endUser();
                        break;
                    
                    // case 'bookmarks':
                    // $this->bookmarkService->getSearchResult($item);
                    // break;
                    
                    default:
                        continue;
                }
                
                if (! $item->isValid())
                    continue;
                
                $hl1 = '';
                $hl2 = '';
                if ($item->getHighlighting() !== null && is_array($item->getHighlighting())) {
                    if (sizeof($item->getHighlighting()) >= 1)
                        $hl1 = '... ' . $item->getHighlighting()[0] . ' ...';
                    if (sizeof($item->getHighlighting()) > 1)
                        $hl2 = '... ' . $item->getHighlighting()[1] . ' ...';
                    if (sizeof($item->getHighlighting()) > 2)
                        $hl1 .= '... ' . $item->getHighlighting()[2] . ' ...';
                    if (sizeof($item->getHighlighting()) > 3)
                        $hl2 .= '... ' . $item->getHighlighting()[3] . ' ...';
                }
                
                $item->setLine(1, $item->getPath());
                $item->setLine(2, $hl1);
                $item->setLine(3, $hl2);
                
                $results[] = $item->toArray();
            }
            
            // $this->miscService->log('>> ' . var_export($results, true));
        }
        
        return array(
            'config' => array(
                'index_files_nextant_only' => (($this->configService->getAppValue('index_files_nextant_only') === '1' && $this->configService->getAppValue('index_files_tree') === '1') ? 1 : 0)
            ),
            'result' => $results
        );
    }

    /**
     * @NoCSRFRequired
     * @PublicPage
     */
    public function suggestRequestPublic($query)
    {
        if (! $this->solrService)
            return false;
        
        if ($this->configService->getAppValue('index_files_sharelink') !== '1')
            return array();
        
        if ($query == null || $query === '')
            return array();
        
        $ierror = null;
        $suggest = $this->solrService->suggest($query, $ierror);
        
        $err = ($ierror == null) ? 0 : $ierror->getCode();
        
        $result = array(
            'status' => $err,
            'result' => $suggest
        );
        
        // $this->miscService->log('>> ' . var_export($result, true));
        
        return $result;
    }
}
