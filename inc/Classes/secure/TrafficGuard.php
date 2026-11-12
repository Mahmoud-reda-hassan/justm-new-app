<?php

class TrafficGuard
{
	private $db;

	private int $minuteLimit;
	private int $fiveMinuteLimit;

	private int $throttleScore;
	private int $blockScore;

	private int $blockMinutes;
	private int $throttleSeconds;

	private int $slowRequestMs;

	private bool $logNormalRequests;

	private string $prefix = 'traffic_guard:';

	// Cache مدة قصيرة لتقليل ضغط MySQL على Shared Hosting.
	private int $blockCacheSeconds = 5;
	private int $logCooldownSeconds = 10;


	public function __construct(
		$db,
		array $config = []
	) {
		$this->db = $db;
		$this->minuteLimit 		 = (int)($config['minute_limit'] ?? 120);
		$this->fiveMinuteLimit 	 = (int)($config['five_minute_limit'] ?? 400);
		$this->throttleScore 	 = (int)($config['throttle_score'] ?? 70);
		$this->blockScore 		 = (int)($config['block_score'] ?? 90);
		$this->blockMinutes 	 = (int)($config['block_minutes'] ?? 10);
		$this->throttleSeconds 	 = (int)($config['throttle_seconds'] ?? 1);
		$this->slowRequestMs 	 = (int)($config['slow_request_ms'] ?? 1000);
		$this->logNormalRequests = (bool)($config['log_normal_requests'] ?? false);
		$this->blockCacheSeconds  = max(1, (int)($config['block_cache_seconds'] ?? 5));
		$this->logCooldownSeconds = max(1, (int)($config['log_cooldown_seconds'] ?? 10));
	}


	/*
    |--------------------------------------------------------------------------
    | CHECK REQUEST
    |--------------------------------------------------------------------------
    */

	public function check( ?int $userId = null ): array {

		$start = microtime(true);

		$ip = $this->getIP();

		$uri =
		$_SERVER['REQUEST_URI']
		?? '/';

		$method =
		$_SERVER['REQUEST_METHOD']
		?? 'GET';

		$userAgent =
		$_SERVER['HTTP_USER_AGENT']
		?? '';

		$sessionId =
		session_status() === PHP_SESSION_ACTIVE
		? session_id()
		: null;


		/*
        |--------------------------------------------------------------------------
        | 1. هل IP محظور بالفعل؟
        |--------------------------------------------------------------------------
        */

		$block = $this->getActiveBlock($ip);

		if ($block) {

			$retryAfter =
			max(
				1,
				strtotime(
					$block['BLOCKED_UNTIL']
				) - time()
			);


			// لا تسجل كل Request أثناء فترة الحظر؛ سجل مرة كل فترة قصيرة فقط.
			$this->logWithCooldown(
				'block',
				$ip,
				$userId,
				$sessionId,
				$method,
				$uri,
				$userAgent,
				429,
				0,
				(int)$block['SCORE'],
				'BLOCK',
				$block['REASON']
			);


			return [

				'allowed' => false,

				'action' => 'BLOCK',

				'reason' =>
				$block['REASON'],

				'score' =>
				(int)$block['SCORE'],

				'retry_after' =>
				$retryAfter,

				'_start' =>
				$start
			];
		}


		/*
        |--------------------------------------------------------------------------
        | 2. Rate counters
        |--------------------------------------------------------------------------
        */

		$rate =
		$this->rateCheck($ip);


		/*
        |--------------------------------------------------------------------------
        | 3. Bot Score
        |--------------------------------------------------------------------------
        */

		$score =
		$this->calculateScore(
			$ip,
			$uri,
			$userAgent,
			$rate,
			$userId
		);


		/*
        |--------------------------------------------------------------------------
        | 4. Rate limit
        |--------------------------------------------------------------------------
        |
        | تجاوز الحد لا يعني Block مباشر.
        | يتم تحويله إلى Score، ثم القرار النهائي يكون حسب
        | throttleScore / blockScore.
        |--------------------------------------------------------------------------
        */

		if ($rate['minute'] > $this->minuteLimit) {
			$score += 20;
		}

		if ($rate['five_minutes'] > $this->fiveMinuteLimit) {
			$score += 25;
		}

		// تجاوز كبير جدًا يستحق نقاطًا إضافية.
		if ($rate['minute'] > ($this->minuteLimit * 2)) {
			$score += 15;
		}

		if ($rate['five_minutes'] > ($this->fiveMinuteLimit * 2)) {
			$score += 20;
		}


		/*
        |--------------------------------------------------------------------------
        | 5. Bot شديد الخطورة
        |--------------------------------------------------------------------------
        */

		if ($score >= $this->blockScore) {

			$this->block(
				$ip,
				$score,
				'HIGH_BOT_SCORE'
			);


			$this->log(
				$ip,
				$userId,
				$sessionId,
				$method,
				$uri,
				$userAgent,
				429,
				0,
				$score,
				'BLOCK',
				'HIGH_BOT_SCORE'
			);


			return [

				'allowed' => false,

				'action' => 'BLOCK',

				'reason' =>
				'HIGH_BOT_SCORE',

				'score' =>
				$score,

				'retry_after' =>
				$this->blockMinutes * 60,

				'_start' =>
				$start
			];
		}


		/*
        |--------------------------------------------------------------------------
        | 6. Suspicious
        |--------------------------------------------------------------------------
        */

		if ($score >= $this->throttleScore) {

			// لا تعمل INSERT لكل طلب في حالة THROTTLE.
			$this->logWithCooldown(
				'throttle',
				$ip,
				$userId,
				$sessionId,
				$method,
				$uri,
				$userAgent,
				null,
				0,
				$score,
				'THROTTLE',
				'SUSPICIOUS'
			);


			/*
             * لا نمنع الطلب مباشرة.
             *
             * نضع تأخير بسيط.
             */

			usleep(
				$this->throttleSeconds * 1000000
			);


			return [

				'allowed' => true,

				'action' => 'THROTTLE',

				'reason' =>
				'SUSPICIOUS',

				'score' =>
				$score,

				'_start' =>
				$start
			];
		}


		/*
        |--------------------------------------------------------------------------
        | 7. Normal
        |--------------------------------------------------------------------------
        */

		return [

			'allowed' => true,

			'action' => 'ALLOW',

			'score' =>
			$score,

			'ip' =>
			$ip,

			'user_id' =>
			$userId,

			'session_id' =>
			$sessionId,

			'method' =>
			$method,

			'uri' =>
			$uri,

			'user_agent' =>
			$userAgent,

			'_start' =>
			$start
		];
	}


	/*
    |--------------------------------------------------------------------------
    | RATE CHECK
    |--------------------------------------------------------------------------
    */

	private function rateCheck( string $ip ): array {

		/*
         * APCu غير موجود
         *
         * لا نمنع المستخدم.
         */

		if (!function_exists('apcu_inc')) {

			return [

				'allowed' => true,

				'minute' => 0,

				'five_minutes' => 0
			];
		}


		/*
         * Minute
         */

		$minuteKey =
		$this->prefix .
		'm:' .
		hash('sha256', $ip) .
		':' .
		date('YmdHi');


		if (!apcu_exists($minuteKey)) {

			apcu_add(
				$minuteKey,
				0,
				120
			);
		}


		$minute =
		(int)apcu_inc(
			$minuteKey
		);


		/*
         * Five minutes
         */

		$fiveBucket =
		(int)floor(
			time() / 300
		);


		$fiveKey =
		$this->prefix .
		'5:' .
		hash('sha256', $ip) .
		':' .
		$fiveBucket;


		if (!apcu_exists($fiveKey)) {

			apcu_add(
				$fiveKey,
				0,
				600
			);
		}


		$five =
		(int)apcu_inc(
			$fiveKey
		);


		return [

			'allowed' =>
			$minute <=
			$this->minuteLimit &&
			$five <=
			$this->fiveMinuteLimit,

			'minute' =>
			$minute,

			'five_minutes' =>
			$five
		];
	}


	/*
    |--------------------------------------------------------------------------
    | BOT SCORE
    |--------------------------------------------------------------------------
    */

	private function calculateScore(
		string $ip,
		string $uri,
		string $userAgent,
		array $rate,
		?int $userId
	): int {

		$score = 0;


		/*
        |--------------------------------------------------------------------------
        | Requests
        |--------------------------------------------------------------------------
        */

		if ($rate['minute'] >= 80) {
			$score += 10;
		}

		if ($rate['minute'] >= 120) {
			$score += 20;
		}

		if ($rate['minute'] >= 160) {
			$score += 30;
		}

		if ($rate['minute'] >= 200) {
			$score += 40;
		}


		/*
        |--------------------------------------------------------------------------
        | User Agent
        |--------------------------------------------------------------------------
        */

		if ($userAgent === '') {

			$score += 20;

		} else {

			$patterns = [

				'curl',
				'wget',
				'python',
				'python-requests',
				'scrapy',
				'selenium',
				'phantomjs',
				'headless',
				'postmanruntime',
				'go-http-client',
				'java',
				'crawler',
				'spider',
				'bot'

			];


			foreach ($patterns as $pattern) {

				if (
					stripos(
						$userAgent,
						$pattern
					) !== false
				) {

					$score += 35;

					break;
				}
			}
		}


		/*
        |--------------------------------------------------------------------------
        | URLs مشبوهة
        |--------------------------------------------------------------------------
        */

		$suspiciousPaths = [

			'/.env',
			'/.git/',
			'/wp-admin',
			'/wp-login',
			'/xmlrpc.php',
			'/phpmyadmin',
			'/config.php',
			'/vendor/',
			'/cgi-bin/'

		];


		foreach (
			$suspiciousPaths
			as $path
		) {

			if (
				stripos(
					$uri,
					$path
				) !== false
			) {

				$score += 45;

				break;
			}
		}


		/*
        |--------------------------------------------------------------------------
        | عدم وجود User
        |--------------------------------------------------------------------------
        */

		/*
         * لا نعتبر عدم Login دليل Bot.
         *
         * لكن مع وجود مؤشرات أخرى نرفع Score.
         */

		if (
			$userId === null &&
			$rate['minute'] >= 100
		) {

			$score += 10;
		}


		/*
        |--------------------------------------------------------------------------
        | نفس URL بشكل متكرر
        |--------------------------------------------------------------------------
        */

		$sameUrl =
		$this->sameUrlCount(
			$ip,
			$uri
		);


		if ($sameUrl >= 20) {

			$score += 10;
		}

		if ($sameUrl >= 50) {

			$score += 15;
		}

		if ($sameUrl >= 100) {

			$score += 20;
		}


		return min(
			100,
			$score
		);
	}
	

	/*
    |--------------------------------------------------------------------------
    | SAME URL COUNTER
    |--------------------------------------------------------------------------
    */

	private function sameUrlCount(
		string $ip,
		string $uri
	): int {

		if (!function_exists('apcu_inc')) {
			return 0;
		}


		$key =
		$this->prefix .
		'url:' .
		hash(
			'sha256',
			$ip . '|' . $uri
		) .
		':' .
		date('YmdHi');


		if (!apcu_exists($key)) {

			apcu_add(
				$key,
				0,
				120
			);
		}


		return (int)apcu_inc($key);
	}


	/*
    |--------------------------------------------------------------------------
    | FINISH
    |--------------------------------------------------------------------------
    */

	public function finish(
		array $context,
		int $statusCode
	): void {

		if (
			!isset(
				$context['_start']
			)
		) {
			return;
		}


		$responseTime =
		(int)(
			(
				microtime(true)
				-
				$context['_start']
			) * 1000
		);


		$score =
		(int)(
			$context['score']
			?? 0
		);


		/*
        |--------------------------------------------------------------------------
        | هل نسجل؟
        |--------------------------------------------------------------------------
        */

		$shouldLog =
		$this->logNormalRequests;


		$reason = 'NORMAL';


		/*
         * Slow
         */

		if (
			$responseTime >=
			$this->slowRequestMs
		) {

			$shouldLog = true;

			$reason =
			'SLOW_REQUEST';
		}


		/*
         * HTTP Error
         */

		if ($statusCode >= 400) {

			$shouldLog = true;

			$reason =
			'HTTP_' . $statusCode;
		}


		/*
         * Suspicious
         */

		if ($score >= 50) {

			$shouldLog = true;

			$reason =
			'SUSPICIOUS';
		}


		if (!$shouldLog) {
			return;
		}


		$this->log(

			$context['ip'],

			$context['user_id']
			?? null,

			$context['session_id']
			?? null,

			$context['method'],

			$context['uri'],

			$context['user_agent'],

			$statusCode,

			$responseTime,

			$score,

			$context['action']
			?? 'ALLOW',

			$reason
		);
	}


	/*
    |--------------------------------------------------------------------------
    | LOG COOLDOWN
    |--------------------------------------------------------------------------
    */

	private function logWithCooldown(
		string $type,
		string $ip,
		?int $userId,
		?string $sessionId,
		string $method,
		string $uri,
		?string $userAgent,
		?int $status,
		int $responseTime,
		int $score,
		string $action,
		string $reason
	): void {

		if (function_exists('apcu_add')) {
			$key = $this->prefix . 'log:' . $type . ':' . hash('sha256', $ip . '|' . $uri);
			if (!apcu_add($key, 1, $this->logCooldownSeconds)) {
				return;
			}
		}

		$this->log(
			$ip, $userId, $sessionId, $method, $uri, $userAgent,
			$status, $responseTime, $score, $action, $reason
		);
	}


	/*
    |--------------------------------------------------------------------------
    | LOG
    |--------------------------------------------------------------------------
    */

	private function log(
		string $ip,
		?int $userId,
		?string $sessionId,
		string $method,
		string $uri,
		?string $userAgent,
		?int $status,
		int $responseTime,
		int $score,
		string $action,
		string $reason
	): void {

		$sql = "
            INSERT INTO traffic_logs
            (
                CREATED_AT,
                IP_ADDRESS,
                USER_ID,
                SESSION_ID,
                REQUEST_METHOD,
                REQUEST_URI,
                USER_AGENT,
                HTTP_STATUS,
                RESPONSE_TIME_MS,
                BOT_SCORE,
                ACTION,
                REASON
            )
            VALUES
            (
                NOW(6),
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ";


		$stmt =
		$this->db->prepare($sql);


		$stmt->execute([

			$ip,

			$userId,

			$sessionId,

			$method,

			$uri,

			$userAgent,

			$status,

			$responseTime,

			$score,

			$action,

			$reason
		]);
	}


	/*
    |--------------------------------------------------------------------------
    | BLOCK
    |--------------------------------------------------------------------------
    */

	private function block(
		string $ip,
		int $score,
		string $reason
	): void {

		/*
         * لا نكرر Block لنفس IP.
         * افحص APCu أولًا لتقليل SELECTs على Shared Hosting.
         */

		$cacheKey = $this->prefix . 'block:' . hash('sha256', $ip);

		if (function_exists('apcu_fetch')) {
			$cached = apcu_fetch($cacheKey, $cacheFound);

			if ($cacheFound && is_array($cached) && isset($cached['BLOCKED_UNTIL'])) {
				if (strtotime($cached['BLOCKED_UNTIL']) > time()) {
					return;
				}
			}
		}

		$stmt =
		$this->db->prepare("
                SELECT ID
                FROM traffic_blocks
                WHERE IP_ADDRESS = ?
                  AND BLOCKED_UNTIL > NOW()
                LIMIT 1
            ");

		$stmt->execute([$ip]);

		if ($stmt->fetchColumn()) {
			return;
		}


		$stmt =
		$this->db->prepare("
                INSERT INTO traffic_blocks
                (
                    IP_ADDRESS,
                    BLOCKED_FROM,
                    BLOCKED_UNTIL,
                    SCORE,
                    REASON
                )
                VALUES
                (
                    ?,
                    NOW(),
                    DATE_ADD(
                        NOW(),
                        INTERVAL ? MINUTE
                    ),
                    ?,
                    ?
                )
            ");


		$stmt->execute([

			$ip,

			$this->blockMinutes,

			$score,

			$reason
		]);

		// اجعل الـ Block يظهر فورًا في نفس worker.
		if (function_exists('apcu_store')) {
			apcu_store(
				$this->prefix . 'block:' . hash('sha256', $ip),
				[
					'SCORE' => $score,
					'BLOCKED_UNTIL' => date('Y-m-d H:i:s', time() + ($this->blockMinutes * 60)),
					'REASON' => $reason
				],
				$this->blockCacheSeconds
			);
		}
	}


	/*
    |--------------------------------------------------------------------------
    | ACTIVE BLOCK
    |--------------------------------------------------------------------------
    */

	private function getActiveBlock(
		string $ip
	): ?array {

		$key = $this->prefix . 'block:' . hash('sha256', $ip);

		// APCu متاح: استخدمه كـ cache لمدة قصيرة لتجنب SELECT في كل Request.
		if (function_exists('apcu_fetch')) {
			$cached = apcu_fetch($key, $success);

			if ($success) {
				if (is_array($cached) && isset($cached['BLOCKED_UNTIL'])) {
					if (strtotime($cached['BLOCKED_UNTIL']) > time()) {
						return $cached;
					}
					apcu_delete($key);
					return null;
				}

				// Negative cache: لا يوجد Block حاليًا.
				if (is_array($cached) && isset($cached['__none'])) {
					return null;
				}

				// أي قيمة Cache غير معروفة: تجاهلها وارجع إلى DB.
			}
		}

		$stmt = $this->db->prepare("
                SELECT
                    SCORE,
                    BLOCKED_UNTIL,
                    REASON
                FROM traffic_blocks
                WHERE IP_ADDRESS = ?
                  AND BLOCKED_UNTIL > NOW()
                ORDER BY ID DESC
                LIMIT 1
            ");

		$stmt->execute([$ip]);

		$result = $stmt->fetch(PDO::FETCH_ASSOC);

		if (function_exists('apcu_store')) {
			if ($result) {
				apcu_store($key, $result, $this->blockCacheSeconds);
			} else {
				// عدم وجود Block يتم تخزينه 5 ثوانٍ فقط.
				apcu_store($key, ['__none' => true], $this->blockCacheSeconds);
			}
		}

		return $result ?: null;
	}


	/*
    |--------------------------------------------------------------------------
    | IP
    |--------------------------------------------------------------------------
    */

	private function getIP(): string
	{
		/*
         * لا تستخدم X-Forwarded-For
         * إلا لو عندك Proxy موثوق.
         */

		return $_SERVER['REMOTE_ADDR']
		?? '0.0.0.0';
	}


	/*
    |--------------------------------------------------------------------------
    | REPORT
    |--------------------------------------------------------------------------
    */

	public function report(
		int $hours = 24
	): array {

		$stmt =
		$this->db->prepare("
                SELECT

                    COUNT(*) AS TOTAL,

                    COUNT(
                        DISTINCT IP_ADDRESS
                    ) AS UNIQUE_IPS,

                    SUM(
                        ACTION = 'BLOCK'
                    ) AS BLOCKED,

                    SUM(
                        ACTION = 'THROTTLE'
                    ) AS THROTTLED,

                    SUM(
                        BOT_SCORE >= 50
                    ) AS SUSPICIOUS,

                    AVG(
                        RESPONSE_TIME_MS
                    ) AS AVG_RESPONSE_MS,

                    MAX(
                        RESPONSE_TIME_MS
                    ) AS MAX_RESPONSE_MS

                FROM traffic_logs

                WHERE CREATED_AT >=
                    DATE_SUB(
                        NOW(),
                        INTERVAL ? HOUR
                    )
            ");


		$stmt->execute([
			$hours
		]);


		return $stmt->fetch(
			PDO::FETCH_ASSOC
		);
	}

	/*
    |--------------------------------------------------------------------------
    | TOP IPS
    |--------------------------------------------------------------------------
    */

	public function topIPs(
		int $hours = 24,
		int $limit = 20
	): array {

		$limit =
		max(
			1,
			min(100, $limit)
		);


		$stmt =
		$this->db->prepare("
                SELECT

                    IP_ADDRESS,

                    COUNT(*) AS REQUESTS,

                    SUM(
                        ACTION = 'BLOCK'
                    ) AS BLOCKED,

                    SUM(
                        ACTION = 'THROTTLE'
                    ) AS THROTTLED,

                    MAX(
                        BOT_SCORE
                    ) AS MAX_SCORE,

                    AVG(
                        RESPONSE_TIME_MS
                    ) AS AVG_RESPONSE_MS

                FROM traffic_logs

                WHERE CREATED_AT >=
                    DATE_SUB(
                        NOW(),
                        INTERVAL ? HOUR
                    )

                GROUP BY IP_ADDRESS

                ORDER BY REQUESTS DESC

                LIMIT $limit
            ");


		$stmt->execute([
			$hours
		]);


		return $stmt->fetchAll(
			PDO::FETCH_ASSOC
		);
	}
	

	/*
    |--------------------------------------------------------------------------
    | TOP APIs
    |--------------------------------------------------------------------------
    */

	public function topAPIs(
		int $hours = 24,
		int $limit = 20
	): array {

		$limit =
		max(
			1,
			min(100, $limit)
		);


		$stmt =
		$this->db->prepare("
                SELECT

                    REQUEST_URI,

                    COUNT(*) AS REQUESTS,

                    SUM(
                        ACTION = 'BLOCK'
                    ) AS BLOCKED,

                    AVG(
                        RESPONSE_TIME_MS
                    ) AS AVG_RESPONSE_MS,

                    MAX(
                        RESPONSE_TIME_MS
                    ) AS MAX_RESPONSE_MS

                FROM traffic_logs

                WHERE CREATED_AT >=
                    DATE_SUB(
                        NOW(),
                        INTERVAL ? HOUR
                    )

                GROUP BY REQUEST_URI

                ORDER BY REQUESTS DESC

                LIMIT $limit
            ");


		$stmt->execute([
			$hours
		]);


		return $stmt->fetchAll(
			PDO::FETCH_ASSOC
		);
	}


	/*
    |--------------------------------------------------------------------------
    | HOURLY TRAFFIC
    |--------------------------------------------------------------------------
    */

	public function hourly(
		int $days = 7
	): array {

		$stmt =
		$this->db->prepare("
                SELECT

                    DATE_FORMAT(
                        CREATED_AT,
                        '%Y-%m-%d %H:00:00'
                    ) AS HOUR,

                    COUNT(*) AS REQUESTS,

                    COUNT(
                        DISTINCT IP_ADDRESS
                    ) AS IPS,

                    SUM(
                        ACTION = 'BLOCK'
                    ) AS BLOCKED,

                    AVG(
                        RESPONSE_TIME_MS
                    ) AS AVG_RESPONSE_MS

                FROM traffic_logs

                WHERE CREATED_AT >=
                    DATE_SUB(
                        NOW(),
                        INTERVAL ? DAY
                    )

                GROUP BY
                    DATE_FORMAT(
                        CREATED_AT,
                        '%Y-%m-%d %H:00:00'
                    )

                ORDER BY HOUR
            ");


		$stmt->execute([
			$days
		]);


		return $stmt->fetchAll(
			PDO::FETCH_ASSOC
		);
	}


	/*
    |--------------------------------------------------------------------------
    | CLEANUP
    |--------------------------------------------------------------------------
    */

	public function cleanup(
		int $days = 7
	): int {

		$stmt =
		$this->db->prepare("
                DELETE FROM traffic_logs

                WHERE CREATED_AT <
                    DATE_SUB(
                        NOW(),
                        INTERVAL ? DAY
                    )
            ");


		$stmt->execute([
			$days
		]);


		return $stmt->rowCount();
	}
}