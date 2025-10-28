# ACE Framework - 전체 코드 검증 보고서

**검증 날짜**: 2025-10-28
**검증 범위**: ace/ 및 app/ 디렉토리의 모든 PHP 파일

---

## 요약

총 **32개 PHP 파일** 검증 완료

### 심각도 분류
- 🔴 **Critical (즉시 수정 필요)**: 6개
- 🟡 **Warning (수정 권장)**: 8개
- 🔵 **Info (개선 권장)**: 5개

---

## 🔴 Critical Issues (즉시 수정 필요)

### 1. **미들웨어 인터페이스 불일치**
**파일**: `app/Http/Middleware/AuthMiddleware.php`

**문제**:
```php
// MiddlewareInterface 정의
public function handle(ServerRequestInterface $request, callable $next): ResponseInterface;

// AuthMiddleware 실제 구현
public function handle(ServerRequestInterface $request): ServerRequestInterface; // ❌ 시그니처 불일치
```

**영향**: 런타임 에러 발생 가능

**해결방법**:
```php
public function handle(ServerRequestInterface $request, callable $next): ResponseInterface
{
    // Get token from Authorization header
    $authHeader = $request->getHeader('Authorization')[0] ?? '';

    if (empty($authHeader)) {
        return new JsonResponse(['error' => 'Missing authorization token'], 401);
    }

    // Extract token (Bearer <token>)
    if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        return new JsonResponse(['error' => 'Invalid authorization format'], 401);
    }

    $token = $matches[1];

    // Validate token
    $authService = new AuthService();
    $user = $authService->getCurrentUser($token);

    if (!$user) {
        return new JsonResponse(['error' => 'Invalid or expired token'], 401);
    }

    // Store user in request attribute for later use
    $request = $request->withAttribute('auth_user', $user);

    return $next($request);
}
```

---

### 2. **오타: SpiEmitter → SapiEmitter**
**파일**: `ace/Foundation/App.php:21`

**문제**:
```php
(new SpiEmitter())->emit($response); // ❌ 클래스명 오타
```

**해결방법**:
```php
(new SapiEmitter())->emit($response); // ✅
```

---

### 3. **Log 클래스 로드되지 않음**
**파일**:
- `ace/Database/Db.php:14, 65`
- `ace/Database/MysqlConnector.php:28, 40`
- `ace/Database/SqliteConnector.php:34, 45`
- `ace/Http/Router.php:18`

**문제**:
```php
Log::w('INFO', 'Db class initialized.'); // ❌ Log 클래스가 로드되지 않음
```

`ace/Support/log.php`가 `boot.php`에서 require되지 않아 Log 클래스를 사용할 수 없습니다.

**해결방법 1**: boot.php에 추가
```php
// ace/Support/boot.php
\setRequire(__DIR__ . '/path.php');
\setRequire(__DIR__ . '/log.php');      // 추가
\setRequire(__DIR__ . '/handler.php');
```

**해결방법 2**: Log 호출 제거
```php
// 모든 Log::w() 호출을 제거하거나 조건부로 변경
if (class_exists('\\ACE\\Support\\Log')) {
    Log::w('INFO', 'Db class initialized.');
}
```

---

### 4. **네임스페이스 오류**
**파일**: `ace/Support/handler.php:204, 232`

**문제**:
```php
<?php namespace ACE\Support;

// ... 코드 ...

set_error_handler("\BOOT\handler");           // ❌ \BOOT 네임스페이스는 존재하지 않음
set_exception_handler('\BOOT\exception_handler'); // ❌
```

**해결방법**:
```php
set_error_handler("\\ACE\\Support\\handler");
set_exception_handler('\\ACE\\Support\\exception_handler');
```

---

### 5. **Core.php에서 존재하지 않는 클래스 등록**
**파일**: `ace/Foundation/Core.php:36-49`

**문제**:
```php
$this->singleton(Security::class, fn() => new Security());  // ❌ 파일 로드 안됨
$this->singleton(Crypt::class, fn() => new Crypt());        // ❌
$this->singleton(Input::class, fn() => new Input());        // ❌
$this->singleton(Output::class, fn() => new Output());      // ❌
$this->singleton(Session::class, fn() => new Session());    // ❌
$this->singleton(Dev::class, fn() => new Dev());            // ❌
```

이 클래스들의 파일(`security.php`, `crypt.php`, `input.php`, `output.php`, `session.php`, `dev.php`)이 `boot.php`에서 로드되지 않습니다.

**해결방법**:
1. 사용하지 않는 서비스 등록 제거
2. 또는 필요한 파일들을 boot.php에서 로드

---

### 6. **path.php의 정의되지 않은 상수**
**파일**: `ace/Support/path.php`

**문제**:
```php
define('APPPATH', PROJECTPATH.DIRECTORY_SEPARATOR.'app');    // ❌ PROJECTPATH 미정의
define('WORKSPATH', ...);  // ❌ 미정의
define('CACHEPATH', ...);  // ❌ 미정의
define('LOGPATH', ...);    // ❌ 미정의
```

**해결방법**: path.php 전체를 BASE_PATH 기준으로 재작성하거나 삭제

---

## 🟡 Warning (수정 권장)

### 7. **중복된 미들웨어 파일**
**파일**:
- `app/Http/Middleware/Authenticate.php` (더미 예제)
- `app/Http/Middleware/AuthMiddleware.php` (실제 구현)

**문제**: `Authenticate.php`는 하드코딩된 토큰("Bearer my-secret-token")을 사용하는 예제 파일입니다.

**해결방법**: `Authenticate.php` 삭제

---

### 8. **Kernel.php에서 잘못된 미들웨어 참조**
**파일**: `app/Http/Kernel.php:24`

**문제**:
```php
'api' => [
    \APP\Http\Middleware\Authenticate::class,  // ❌ 더미 예제 파일
],
```

**해결방법**:
```php
'api' => [
    \APP\Http\Middleware\AuthMiddleware::class,  // ✅ 실제 구현
],
```

---

### 9. **사용되지 않는 Support 파일들**
**파일**:
- `ace/Support/security.php`
- `ace/Support/session.php`
- `ace/Support/crypt.php`
- `ace/Support/config.php`
- `ace/Support/dev.php`
- `ace/Support/log.php`

**문제**: `boot.php`에서 require되지 않아 사용할 수 없습니다.

**해결방법**:
1. 필요한 파일만 boot.php에 추가
2. 불필요한 파일 삭제

---

### 10. **사용되지 않는 Http 파일들**
**파일**:
- `ace/Http/input.php`
- `ace/Http/output.php`

**문제**: 어디서도 require/include되지 않습니다. 레거시 코드로 보입니다.

**해결방법**: 삭제

---

### 11. **DatabaseDriverInterface의 getLastInsertId() 누락**
**파일**: `ace/Database/DatabaseDriverInterface.php`

**문제**: `Model.php:109`와 `AuthService.php:48`에서 `getLastInsertId()`를 호출하지만, 인터페이스에 정의되지 않았습니다.

**해결방법**: 인터페이스에 메서드 추가
```php
public function getLastInsertId(): int;
```

그리고 각 Connector에서 구현:
```php
// MysqlConnector
public function getLastInsertId(): int
{
    return $this->conn->insert_id;
}

// SqliteConnector
public function getLastInsertId(): int
{
    return (int) $this->conn->lastInsertId();
}
```

---

### 12. **프로젝트 특정 함수들**
**파일**: `ace/Support/default.php:53-60`

**문제**:
```php
function _L($str) { ... }                    // ❌ 특정 프로젝트 전용
function redkokoPriceFormat(...) { ... }     // ❌ 특정 프로젝트 전용
```

**해결방법**: 이 함수들을 제거하거나 주석 처리

---

### 13. **Model.php에서 $fillable 필터링 이슈**
**파일**: `ace/Database/Model.php:94-96, 120-122`

**문제**: `$fillable`이 비어있으면 필터링을 하지 않는데, 이는 mass assignment 취약점이 될 수 있습니다.

**해결방법**:
```php
// 명시적으로 fillable이 정의되지 않은 경우 경고
if (empty(static::$fillable)) {
    throw new \Exception('No fillable fields defined for ' . static::class);
}
```

---

### 14. **BaseService에 DB 인스턴스 전달 누락**
**파일**: `ace/Service/BaseService.php:27-41`

**문제**: `transaction()` 메서드가 `DB::getInstance()`를 호출하지만, DB 클래스에는 `getInstance()` 메서드가 없습니다.

**해결방법**:
```php
protected function transaction(callable $callback)
{
    $dbManager = app(\ACE\Database\Db::class);
    $db = $dbManager->driver(env('DB_CONNECTION', 'mysql'), true);

    try {
        $db->beginTransaction();
        $result = $callback();
        $db->commit();
        return $result;
    } catch (\Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
```

---

## 🔵 Info (개선 권장)

### 15. **DB 클래스에 getInstance() 메서드 없음**
**파일**: `ace/Database/Db.php`

**문제**: 싱글톤 패턴처럼 보이지만 `getInstance()` 메서드가 없습니다.

**권장사항**: DI 컨테이너를 통해 사용하거나 getInstance() 추가
```php
public static function getInstance(): self
{
    return app(self::class);
}
```

---

### 16. **일관성 없는 에러 처리**
**관찰**: 일부 클래스는 Exception을 throw하고, 일부는 http_response_code + echo + exit를 사용합니다.

**권장사항**: 통일된 에러 처리 방식 사용

---

### 17. **주석 처리된 코드**
**파일**: `ace/Support/handler.php:140-170`

**권장사항**: 사용하지 않는 코드 삭제

---

### 18. **CodeGenerator의 긴 메서드**
**파일**: `ace/Database/CodeGenerator.php`

**관찰**: 일부 메서드가 100줄 이상입니다.

**권장사항**: AI Agent Guide의 기준(max 100줄)에 맞춰 리팩토링

---

### 19. **Router에서 getClassNameFromFile 미구현**
**파일**: `ace/Http/Router.php:92`

**문제**: 메서드 시그니처만 있고 구현이 없습니다.

**권장사항**: 구현 추가 또는 제거

---

## 수정 우선순위

### 즉시 수정 (오늘)
1. ✅ BaseService의 transaction() 메서드 수정
2. ✅ AuthMiddleware 인터페이스 수정
3. ✅ App.php의 오타 수정
4. ✅ Kernel.php 미들웨어 참조 수정
5. ✅ Authenticate.php (더미) 삭제

### 단기 수정 (1-2일 내)
6. ✅ Log 클래스 문제 해결 (제거 또는 로드)
7. ✅ handler.php 네임스페이스 수정
8. ✅ Core.php 사용하지 않는 서비스 등록 제거
9. ✅ DatabaseDriverInterface에 getLastInsertId() 추가
10. ✅ 사용하지 않는 파일 정리 (input.php, output.php, security.php 등)

### 중기 개선 (1주일 내)
11. path.php 리팩토링 또는 제거
12. _L(), redkokoPriceFormat() 함수 제거
13. Router의 getClassNameFromFile 구현
14. 일관된 에러 처리 방식 도입

---

## 테스트 권장사항

### 단위 테스트
- [ ] Model CRUD 작업
- [ ] AuthService 등록/로그인
- [ ] TokenManager 토큰 생성/검증
- [ ] BaseService transaction

### 통합 테스트
- [ ] 전체 인증 플로우 (등록 → 로그인 → API 호출)
- [ ] 미들웨어 체인
- [ ] 다중 테이블 트랜잭션

### 수동 테스트
```bash
# 1. 서버 시작
./ace serve

# 2. 사용자 등록
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"test123","name":"Test User"}'

# 3. 로그인
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"test123"}'

# 4. 인증된 API 호출
curl http://localhost:8080/api/auth/me \
  -H "Authorization: Bearer <token>"
```

---

## 결론

ACE Framework는 **핵심 기능은 잘 설계**되어 있지만, **레거시 코드와 미사용 파일들**로 인해 혼란스러운 상태입니다.

### 긍정적인 부분
✅ DBML 기반 자동 생성 아이디어 우수
✅ 인증 시스템 구조 잘 설계됨
✅ Model 패턴 간단하고 명확함
✅ BaseService로 AI-safe 패턴 제공

### 개선 필요 부분
❌ 사용하지 않는 파일 너무 많음 (Support 디렉토리)
❌ Log 클래스 문제로 실행 불가능
❌ 미들웨어 인터페이스 불일치
❌ 문서화되지 않은 상수 및 의존성

### 다음 단계
1. **즉시 수정 항목 처리** (1-2시간)
2. **불필요한 파일 제거** (30분)
3. **테스트 실행** (1시간)
4. **문서 업데이트** (30분)

**예상 총 작업 시간**: 3-4시간

---

**검증자**: Claude (AI Assistant)
**검증 도구**: 정적 분석, 코드 리뷰, 패턴 검증
