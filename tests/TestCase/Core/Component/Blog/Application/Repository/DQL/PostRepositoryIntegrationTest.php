<?php

declare(strict_types=1);

/*
 * This file is part of the Explicit Architecture POC,
 * which is created on top of the Symfony Demo application.
 *
 * (c) Herberto Graça <herberto.graca@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Acme\App\Test\TestCase\Core\Component\Blog\Application\Repository\DQL;

use Acme\App\Core\Component\Blog\Application\Repository\DQL\PostRepository;
use Acme\App\Core\Component\Blog\Domain\Post\Post;
use Acme\App\Core\Component\Blog\Domain\Post\PostId;
use Acme\App\Core\Port\Persistence\DQL\DqlQueryBuilderInterface;
use Acme\App\Core\Port\Persistence\QueryServiceRouterInterface;
use Acme\App\Infrastructure\Persistence\Doctrine\DqlPersistenceService;
use Acme\App\Test\Framework\AbstractIntegrationTest;

/**
 * @medium
 *
 * @internal
 */
final class PostRepositoryIntegrationTest extends AbstractIntegrationTest
{
    /**
     * @var PostRepository
     */
    private $repository;

    /**
     * @var DqlPersistenceService
     */
    private $persistenceService;

    /**
     * @var DqlQueryBuilderInterface
     */
    private $dqlQueryBuilder;

    /**
     * @var QueryServiceRouterInterface
     */
    private $queryService;

    protected function setUp(): void
    {
        $this->repository = self::getService(PostRepository::class);
        $this->persistenceService = self::getService(DqlPersistenceService::class);
        $this->dqlQueryBuilder = self::getService(DqlQueryBuilderInterface::class);
        $this->queryService = self::getService(QueryServiceRouterInterface::class);
    }

    /**
     * @test
     *
     * @throws \Doctrine\DBAL\ConnectionException
     */
    public function upsert_updates_entity(): void
    {
        $newContent = 'some new content';
        $post = $this->findAPost();
        $postId = $post->getId();
        $post->setContent($newContent);
        $this->persistenceService->startTransaction();
        $this->repository->add($post);
        $this->persistenceService->finishTransaction();
        $this->clearDatabaseCache();

        $post = $this->findById($postId);

        self::assertSame($newContent, $post->getContent());
    }

    /**
     * @test
     *
     * @throws \Doctrine\DBAL\ConnectionException
     */
    public function upsert_creates_entity(): void
    {
        $auxiliaryPost = $this->findAPost();

        $post = new Post();
        $post->setAuthorId($auxiliaryPost->getAuthorId());
        $post->setContent($content = 'some new content');
        $post->setTitle($title = 'a title');
        $post->setSummary($summary = 'a summary');

        $this->persistenceService->startTransaction();
        $this->repository->add($post);
        $this->persistenceService->finishTransaction();
        $postId = $post->getId();
        $this->clearDatabaseCache();

        $post = $this->findById($postId);

        self::assertSame($content, $post->getContent());
        self::assertSame($title, $post->getTitle());
        self::assertSame($summary, $post->getSummary());
        self::assertTrue($auxiliaryPost->getAuthorId()->equals($post->getAuthorId()));
    }

    /**
     * @test
     *
     * @throws \Doctrine\DBAL\ConnectionException
     */
    public function delete_removes_the_entity(): void
    {
        $this->expectException(\Acme\App\Core\Port\Persistence\Exception\EmptyQueryResultException::class);

        $post = $this->findAPost();
        $postId = $post->getId();

        $this->persistenceService->startTransaction();
        $this->repository->remove($post);
        $this->persistenceService->finishTransaction();

        $this->clearDatabaseCache();

        $this->findById($postId);
    }

    /**
     * @test
     *
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Doctrine\DBAL\DBALException
     */
    public function delete_removes_the_associated_tags(): void
    {
        $post = $this->findAPost();

        $postId = $post->getId();

        $this->persistenceService->startTransaction();
        $this->repository->remove($post);
        $post = null;
        $this->persistenceService->finishTransaction();

        $this->clearDatabaseCache();

        self::assertSame(0, $this->getTagListCountByPostId($postId));
    }

    private function findById(PostId $id): Post
    {
        $dqlQuery = $this->dqlQueryBuilder->create(Post::class)
            ->where('Post.id = :id')
            ->setParameter('id', $id)
            ->build();

        return $this->queryService->query($dqlQuery)->getSingleResult();
    }

    private function findAPost(): Post
    {
        $dqlQuery = $this->dqlQueryBuilder->create(Post::class)->setMaxResults(1)->build();

        return $this->queryService->query($dqlQuery)->getSingleResult();
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getTagListCountByPostId(PostId $postId): int
    {
        $statement = $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                "SELECT count(`post_id`) as `count` FROM `symfony_demo_post_tag` WHERE `post_id` = '$postId'"
            );

        $result = $statement->fetchAll();

        return (int) $result[0]['count'];
    }
}
