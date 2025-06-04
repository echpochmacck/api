<?php

namespace app\controllers;

use app\models\Task;
use Yii;

use yii\filters\auth\HttpBearerAuth;

class TaskController extends \yii\rest\ActiveController
{
    public $modelClass = '';
    public $enableCsrfValidation = false;

    public function actionIndex()
    {
        return $this->render('index');
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // remove authentication filter
        $auth = $behaviors['authenticator'];
        unset($behaviors['authenticator']);

        // add CORS filter
        $behaviors['corsFilter'] = [
            'class' => \yii\filters\Cors::class,
            'cors' => [
                'Origin' => [isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'http://' . $_SERVER['REMOTE_ADDR']],
                'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
                'Access-Control-Request-Headers' => ['*'],
            ],
            'actions' => [
                'logout' => [
                    'Access-Control-Allow-Credentials' => true,
                ]
            ]
        ];
        $auth = [
            'class' => HttpBearerAuth::class,
            'only' => ['new', 'edit', 'delete'],
        ];

        // re-add authentication filter
        $behaviors['authenticator'] = $auth;
        // avoid authentication on CORS-pre-flight requests (HTTP OPTIONS method)
        $behaviors['authenticator']['except'] = ['options'];

        return $behaviors;
    }
    public function actions()
{
    $actions = parent::actions();

    // disable the "delete" and "create" actions
    unset($actions['delete'], $actions['create']);

    // customize the data provider preparation with the "prepareDataProvider()" method
    $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];

    return $actions;
}

    public function actionNew()
    {
        $model = new Task();
        $model->load(Yii::$app->request->post(), '');
        if ($model->validate()) {
            $model->save(false);
            return $this->asJson([
                'data' => [
                    'id' => $model->id,
                    'title' => $model->title,
                    'description' => $model->description,
                    'parent_id' => $model->parent_id
                ]
            ]);
        } else {
            Yii::$app->response->statusCode = 422;

            return $this->asJson([
                'errors' => $model->errors
            ]);
        }
    }

    public function actionGetTasks()
    {
        $allTasks = Task::find()
            ->select(['*'])
            ->asArray()
            ->all();
        $parents = Task::find()
            ->select(['*'])
            ->where(['parent_id' => null])
            ->asArray()
            ->all();
        $parents = array_map(function ($parent) use ($allTasks) {
            $parent['children'] = Task::getChildren($allTasks, $parent['id']);
            return $parent;
        }, $parents);
        return $this->asJson([
            'tasks' => $parents
        ]);
    }
    public function actionEdit($id)
{
    $model = Task::findOne($id);
    
    if (!$model) {
        Yii::$app->response->statusCode = 404;
        return $this->asJson('');
    }

    $model->load(Yii::$app->request->post(), '');
    
    if ($model->validate()) {
        $model->save(false);
        return $this->asJson([
            'data' => [
                'id' => $model->id,
                'title' => $model->title,
                'description' => $model->description,
                'parent_id' => $model->parent_id
            ]
        ]);
    } else {
        Yii::$app->response->statusCode = 422;
        return $this->asJson([
            'errors' => $model->errors
        ]);
    }
}

    public function actionDelete($id)
    {
        $model = Task::findOne($id);

        if (!$model) {
            Yii::$app->response->statusCode = 404;
            return '';
        }

        $model->delete();

        return $this->asJson(['success' => true]);
    }
}
