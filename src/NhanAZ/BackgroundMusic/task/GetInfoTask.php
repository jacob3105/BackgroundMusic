<?php

declare(strict_types=1);

namespace NhanAZ\BackgroundMusic\task;

use NhanAZ\BackgroundMusic\Main;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Internet;
use pocketmine\utils\InternetRequestResult;
use Symfony\Component\Filesystem\Path;

class GetInfoTask extends AsyncTask {

    public function __construct(
        private string $url
    ) {}

    /**
     * @return void
     */
    public function onRun(): void {
        $this->setResult(Internet::getURL($this->url));
    }

    public function onCompletion(): void {
        $result = $this->getResult();
        if (!$result instanceof InternetRequestResult) {
            return;
        }
		if ($result->getCode() !== 200) { // Error
			@unlink(Path::join(RESOURCE_PACK_PATH));
			return;
		}
        $contents = json_decode($result->getBody(), true);
        $endFile = end($contents);
        foreach ($contents as $content) {
            if ($content["type"] === "dir") {
                $realPath = Path::join(RESOURCE_PACK_PATH, "..", $content["path"]);
                if (!is_dir($realPath)) {
                    @mkdir($realPath);
                }
                Server::getInstance()->getAsyncPool()->submitTask(new GetInfoTask($content["url"]));
            } else {
                $path = Path::join(RESOURCE_PACK_PATH, "..", $content["path"]);
                Server::getInstance()->getAsyncPool()->submitTask(
                    new DownloadTask(
                        $content["download_url"],
                        $path,
                        $endFile["type"] !== "dir" && $content["download_url"] === $endFile["download_url"]
                    ));
            }
        }
    }
}