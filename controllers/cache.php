<?php
namespace Concrete\Package\PageListMap\Controller;
use Concrete\Core\Controller\Controller;
use Concrete\Core\Cache\Level\ExpensiveCache;
use Symfony\Component\HttpFoundation\JsonResponse;

class CacheController extends Controller {
    public function getData($id) {
        $app = \Concrete\Core\Support\Facade\Application::getFacadeApplication();
        $cache = $app->make(ExpensiveCache::class);
        $cacheItem = $cache->getItem('custom_data/' . $id);

        if ($cacheItem->isMiss()) {
            // Data not in cache; generate it
            $data = ['id' => $id, 'content' => $_POST['content']];
            
            $cacheItem->set($data);
            $cacheItem->expiresAfter(3600); // 1 hour
            $cache->save($cacheItem);
        } else {
            $data = $cacheItem->get();
        }

        return new JsonResponse($data);
    }
}
