<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Gallery\Models\Album;
use App\Modules\Gallery\Models\Photo;
use T4\Core\Exception;
use T4\Http\Uploader;
use T4\Mvc\Controller;

class Gallery
    extends Controller
{

    const PAGE_SIZE = 20;

    protected function access($action)
    {
        return !empty($this->app->user) && $this->app->user->hasRole('admin');
    }

    public function actionDefault($page = 1)
    {
        $this->data->itemsCount = Photo::countAll();
        $this->data->pageSize = self::PAGE_SIZE;
        $this->data->activePage = $page;
        $this->data->items = Photo::findAll([
            'order' => 'published DESC',
            'offset' => ($page - 1) * self::PAGE_SIZE,
            'limit' => self::PAGE_SIZE
        ]);
    }


    public function actionPhoto($id = null, $page = 1)
    {
        if ($id == null) {
            $id = $this->app->request->post->parent;
        }
        $this->data->url = $this->app->request->getPath() . '/?page=%d&id=' . $id;
        $album = $this->data->item = Album::findByColumn('__id', $id);
        $this->data->itemsCount = count(Album::findByPK($id)->photos->collect('__id'));
        $this->data->pageSize = self::PAGE_SIZE;
        $this->data->activePage = $page;
        $this->data->albums = Album::findAllByQuery('SELECT __id, title FROM albums WHERE __lft >' . $album->__lft . ' AND __rgt <' . $album->__rgt);
        if ($album->__prt) {
            $this->data->albumParent = Album::findByColumn('__id', $album->__prt);
        }
        $this->data->photos = Photo::findAllByColumn('__album_id', $id, [
            'order' => 'published DESC',
            'offset' => ($page - 1) * self::PAGE_SIZE,
            'limit' => self::PAGE_SIZE
        ]);
    }

    public function actionView($id)
    {
        $this->data->item = Photo::findByPK($id);
    }

    public function actionEdit($__album_id, $id = null)
    {
        if (null === $id || 'new' == $id) {
            $this->data->item = new Photo();
        } else {
            $this->data->item = Photo::findByPK($id);
        }
        $album = $this->data->album = Album::findByColumn('__id', $__album_id);
        if ($album->__prt) {
            $this->data->albumParent = Album::findByColumn('__id', $album->__prt);
        }
    }

    public function actionEditUploadedFiles()
    {
        $request = $this->app->request;
        if ($request->isUploadedArray('image')) {
            $uploader = new Uploader('image');
            $uploader->setPath('/public/gallery/photos');
            $images = $uploader();
            $num = count($images);
            foreach ($images as $image) {
                $item = new Photo();
                $item->fill($this->app->request->post);
                $item->image = $image;
                $item->save();
            }
        } else {
            $num = 1;
            if (!empty($this->app->request->post->id)) {
                $item = Photo::findByPK($this->app->request->post->id);
            } else {
                $item = new Photo();
            }
            $item->fill($this->app->request->post);
            $item
                ->uploadImage('image')
                ->save();
        }
        $album = $this->data->album = Album::findByColumn('__id', $this->app->request->post->__album_id);
        if ($album->__prt) {
            $this->data->albumParent = Album::findByColumn('__id', $album->__prt);
        }
        $album_id = $this->data->album_id = $this->app->request->post->__album_id;
        $this->data->items = Photo::findAllByQuery('SELECT * FROM photos WHERE __album_id=' . $album_id . ' ORDER BY published DESC LIMIT ' . $num);
    }

    public function actionSave()
    {
        $__album_id = $this->app->request->post->__album_id;
        foreach ($this->app->request->post->id as $id) {
            static $num = 0;
            $item = Photo::findByPK($id);
            $item->title = $this->app->request->post->title[$num];
            $item->save();
            $num++;
        }
        if (!$__album_id) {
            $this->redirect('/admin/gallery/');
        }
        $this->redirect('/admin/gallery/photo?id=' . $__album_id);
    }


    public function actionDelete($__album_id = null, $id)
    {
        $item = Photo::findByPK($id);
        $item->delete();
        if (null == $__album_id) {
            $this->redirect('/admin/gallery/');
        }
        $this->redirect('/admin/gallery/photo?id=' . $__album_id);
    }

    /**
     * Albums
     */
    public function actionAlbums()
    {
        $this->data->items = Album::findAllTree();
    }


    public function actionAlbumEdit($id = null, $parent=null)
    {
        if (null === $id || 'new' == $id) {
            $this->data->item = new Album();
            if (null !== $parent) {
                $this->data->item->parent = $parent;
            }
        } else {
            $this->data->item = Album::findByPK($id);
        }
    }

    public function actionEditTopic($id=null, $parent=null)
    {
        if (null === $id || 'new' == $id) {
            $this->data->item = new Topic();
            if (null !== $parent) {
                $this->data->item->parent = $parent;
            }
        } else {
            $this->data->item = Topic::findByPK($id);
        }
    }


    public function actionAlbumSave()
    {
        if (!empty($this->app->request->post->id)) {
            $item = Album::findByPK($this->app->request->post->id);
        } else {
            $item = new Album();
        }
        $item->fill($this->app->request->post);
        $item->save();
        $this->redirect('/admin/gallery/albums');
    }

    public function actionAlbumDelete($id)
    {
        $item = Album::findByPK($id);
        if ($item) {
            $item->delete();
        }
        $this->redirect('/admin/gallery/albums');
    }

    public function actionAlbumUp($id)
    {
        $item = Album::findByPK($id);
        if (empty($item))
            $this->redirect('/admin/gallery/albums');
        $sibling = $item->getPrevSibling();
        if (!empty($sibling)) {
            $item->insertBefore($sibling);
        }
        $this->redirect('/admin/gallery/albums');
    }

    public function actionAlbumDown($id)
    {
        $item = Album::findByPK($id);
        if (empty($item))
            $this->redirect('/admin/gallery/albums');
        $sibling = $item->getNextSibling();
        if (!empty($sibling)) {
            $item->insertAfter($sibling);
        }
        $this->redirect('/admin/gallery/albums');
    }

    public function actionAlbumMoveBefore($id, $to)
    {
        try {
            $item = Album::findByPK($id);
            if (empty($item)) {
                throw new Exception('Source element does not exist');
            }
            $destination = Album::findByPK($to);
            if (empty($destination)) {
                throw new Exception('Destination element does not exist');
            }
            $item->insertBefore($destination);
            $this->data->result = true;
        } catch (Exception $e) {
            $this->data->result = false;
            $this->data->error = $e->getMessage();
        }
    }

    public function actionAlbumMoveAfter($id, $to)
    {
        try {
            $item = Album::findByPK($id);
            if (empty($item)) {
                throw new Exception('Source element does not exist');
            }
            $destination = Album::findByPK($to);
            if (empty($destination)) {
                throw new Exception('Destination element does not exist');
            }
            $item->insertAfter($destination);
            $this->data->result = true;
        } catch (Exception $e) {
            $this->data->result = false;
            $this->data->error = $e->getMessage();
        }
    }
}