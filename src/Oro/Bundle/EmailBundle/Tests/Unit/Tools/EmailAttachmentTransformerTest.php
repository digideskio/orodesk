<?php

namespace Oro\Bundle\EmailBundle\Tests\Unit\Tools;

use Gaufrette\Filesystem;

use Knp\Bundle\GaufretteBundle\FilesystemMap;

use Oro\Bundle\EmailBundle\Form\Model\Factory;
use Oro\Bundle\EmailBundle\Tools\EmailAttachmentTransformer;

class EmailAttachmentTransformerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Filesystem|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $filesystem;

    /**
     * @var FilesystemMap|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $filesystemMap;

    /**
     * @var Factory
     */
    protected $factory;

    /**
     * @var EmailAttachmentTransformer
     */
    protected $emailAttachmentTransformer;

    protected function setUp()
    {
        $this->filesystemMap = $this->getMockBuilder('Knp\Bundle\GaufretteBundle\FilesystemMap')
            ->disableOriginalConstructor()
            ->getMock();

        $this->filesystem = $this->getMockBuilder('Gaufrette\Filesystem')
            ->setMethods(['get'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->filesystemMap->expects($this->once())
            ->method('get')
            ->with('attachments')
            ->will($this->returnValue($this->filesystem));

        $this->factory = new Factory();

        $this->emailAttachmentTransformer = new EmailAttachmentTransformer($this->filesystemMap, $this->factory);
    }

    public function testEntityToModel()
    {
        $attachmentEntity = $this->getMock('Oro\Bundle\EmailBundle\Entity\EmailAttachment');

        $attachmentEntity->expects($this->once())
            ->method('getId')
            ->willReturn(1);

        $emailAttachmentContent = $this->getMock('Oro\Bundle\EmailBundle\Entity\EmailAttachmentContent');
        $emailAttachmentContent->expects($this->once())
            ->method('getContent')
            ->willReturn('Some content');

        $attachmentEntity->expects($this->once())
            ->method('getContent')
            ->willReturn($emailAttachmentContent);

        $emailBody = $this->getMock('Oro\Bundle\EmailBundle\Entity\EmailBody');
        $emailBody->expects($this->once())
            ->method('getCreated')
            ->willReturn('2015-04-13 19:09:32');

        $attachmentEntity->expects($this->once())
            ->method('getEmailBody')
            ->willReturn($emailBody);

        $attachmentModel = $this->emailAttachmentTransformer->entityToModel($attachmentEntity);

        $this->assertInstanceOf('Oro\Bundle\EmailBundle\Form\Model\EmailAttachment', $attachmentModel);
        $this->assertEquals(1, $attachmentModel->getId());
        $this->assertEquals(12, $attachmentModel->getFileSize());
        $this->assertEquals('2015-04-13 19:09:32', $attachmentModel->getModified());
        $this->assertEquals(2, $attachmentModel->getType());
        $this->assertEquals($attachmentEntity, $attachmentModel->getEmailAttachment());
    }

    public function testOroToModel()
    {
        $attachmentOro = $this->getMock('Oro\Bundle\AttachmentBundle\Entity\Attachment');

        $attachmentOro->expects($this->once())
            ->method('getId')
            ->willReturn(1);

        $file = $this->getMock('Oro\Bundle\AttachmentBundle\Entity\File');
        $file->expects($this->once())
            ->method('getOriginalFilename')
            ->willReturn('filename.txt');

        $file->expects($this->once())
            ->method('getFileSize')
            ->willReturn(100);

        $attachmentOro->expects($this->exactly(2))
            ->method('getFile')
            ->willReturn($file);

        $attachmentOro->expects($this->once())
            ->method('getCreatedAt')
            ->willReturn('2015-04-13 19:09:32');

        $attachmentModel = $this->emailAttachmentTransformer->oroToModel($attachmentOro);

        $this->assertInstanceOf('Oro\Bundle\EmailBundle\Form\Model\EmailAttachment', $attachmentModel);
        $this->assertEquals(1, $attachmentModel->getId());
        $this->assertEquals(100, $attachmentModel->getFileSize());
        $this->assertEquals('2015-04-13 19:09:32', $attachmentModel->getModified());
        $this->assertEquals(1, $attachmentModel->getType());
        $this->assertEquals(null, $attachmentModel->getEmailAttachment());
    }

    public function testOroToEntity()
    {
        $attachmentOro = $this->getMock('Oro\Bundle\AttachmentBundle\Entity\Attachment');

        $file = $this->getMock('Oro\Bundle\AttachmentBundle\Entity\File');
        $file->expects($this->once())
            ->method('getOriginalFilename')
            ->willReturn('filename.txt');

        $file->expects($this->exactly(2))
            ->method('getFilename')
            ->willReturn('filename');

        $file->expects($this->once())
            ->method('getMimeType')
            ->willReturn('text/plain');

        $attachmentOro->expects($this->exactly(5))
            ->method('getFile')
            ->willReturn($file);

        $fileContent = $this->getMockBuilder('Gaufrette\File')
            ->disableOriginalConstructor()
            ->getMock();

        $fileContent->expects($this->once())
            ->method('getContent')
            ->willReturn('content');

        $this->filesystem->expects($this->once())
            ->method('get')
            ->with('filename')
            ->willReturn($fileContent);

        $attachmentEntity = $this->emailAttachmentTransformer->oroToEntity($attachmentOro);

        $this->assertInstanceOf('Oro\Bundle\EmailBundle\Entity\EmailAttachment', $attachmentEntity);
        $this->assertEquals($attachmentEntity->getId(), null);
        $this->assertInstanceOf(
            'Oro\Bundle\EmailBundle\Entity\EmailAttachmentContent',
            $attachmentEntity->getContent()
        );
        $this->assertEquals(base64_encode('content'), $attachmentEntity->getContent()->getContent());
        $this->assertEquals('base64', $attachmentEntity->getContent()->getContentTransferEncoding());
        $this->assertEquals($attachmentEntity, $attachmentEntity->getContent()->getEmailAttachment());
        $this->assertEquals('text/plain', $attachmentEntity->getContentType());
        $this->assertEquals('filename.txt', $attachmentEntity->getFileName());
    }

    public function testEntityFromUploadedFile()
    {
        $fileContent = "test attachment\n";

        $uploadedFile = $this
            ->getMockBuilder('Symfony\Component\HttpFoundation\File\UploadedFile')
            ->enableOriginalConstructor()
            ->setConstructorArgs([__DIR__ . '/../Fixtures/attachment/test.txt', ''])
            ->getMock();

        $uploadedFile->expects($this->once())
            ->method('getMimeType')
            ->willReturn('text/plain');

        $uploadedFile->expects($this->once())
            ->method('getClientOriginalName')
            ->willReturn('test.txt');

        $uploadedFile->expects($this->once())
            ->method('getRealPath')
            ->willReturn(__DIR__ . '/../Fixtures/attachment/test.txt');

        $attachmentEntity = $this->emailAttachmentTransformer->entityFromUploadedFile($uploadedFile);

        $this->assertInstanceOf('Oro\Bundle\EmailBundle\Entity\EmailAttachment', $attachmentEntity);
        $content = $attachmentEntity->getContent();
        $this->assertEquals(base64_encode($fileContent), $content->getContent());
        $this->assertEquals('base64', $content->getContentTransferEncoding());

        $this->assertEquals($attachmentEntity->getContentType(), 'text/plain');
        $this->assertEquals($attachmentEntity->getFileName(), 'test.txt');
    }
}
