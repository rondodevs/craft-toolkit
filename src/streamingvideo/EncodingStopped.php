<?php

namespace rondodevs\toolkit\streamingvideo;

use Exception;

/**
 * The encoding job was released from the queue while ffmpeg was running:
 * ffmpeg is killed and the job ends without an error.
 */
class EncodingStopped extends Exception
{
}
