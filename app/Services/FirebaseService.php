<?php

namespace App\Services;

use Google\Cloud\Firestore\FirestoreClient;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Factory;

class FirebaseService
{
    protected string $credentialsPath;
    protected string $projectId;
    protected Factory $factory;

    protected ?FirebaseAuth $auth = null;
    protected ?FirestoreClient $firestore = null;

    public function __construct()
    {
        $path = env('FIREBASE_CREDENTIALS');

        if (!$path) {
            throw new \Exception('FIREBASE_CREDENTIALS is not set in .env');
        }

        $this->credentialsPath = preg_match('/^[A-Za-z]:[\/\\\\]/', $path)
            ? $path
            : base_path($path);

        if (!file_exists($this->credentialsPath)) {
            throw new \Exception('Firebase credentials file not found.');
        }

        $credentials = json_decode(file_get_contents($this->credentialsPath), true);

        if (!is_array($credentials) || empty($credentials['project_id'])) {
            throw new \Exception('Invalid Firebase service account JSON.');
        }

        $this->projectId = env('FIREBASE_PROJECT_ID') ?: $credentials['project_id'];

        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $this->credentialsPath);

        $this->factory = (new Factory)
            ->withServiceAccount($this->credentialsPath)
            ->withProjectId($this->projectId);
    }

    public function auth(): FirebaseAuth
    {
        if (!$this->auth) {
            $this->auth = $this->factory->createAuth();
        }

        return $this->auth;
    }

    public function firestore(): FirestoreClient
    {
        if (!$this->firestore) {
            $this->firestore = new FirestoreClient([
                'projectId' => $this->projectId,
                'keyFilePath' => $this->credentialsPath,
            ]);
        }

        return $this->firestore;
    }
}