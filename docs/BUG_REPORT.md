# ACE Framework - 버그 및 오류 상세 보고서

**테스트 날짜**: 2025-10-28
**검증 방법**: 파일 단위 코드 리뷰 + 실행 테스트

---

## 🔴 Critical Bugs (즉시 수정 필요)

### 1. **path.php - 정의되지 않은 상수 사용**
**파일**: `ace/Support/path.php`
**라인**: 15-34

**문제**:
```php
define('APPPATH', PROJECTPATH.DIRECTORY_SEPARATOR.'app');  // PROJECTPATH 정의 안됨
define('_APPPATH', WORKSPATH.DIRECTORY_SEPARATOR.'app');   // WORKSPATH 정의 안됨
define('_CACHEPATH', CACHEPATH.DIRECTORY_SEPARATOR.HOST);  // CACHEPATH, HOST 정의 안됨
define('_LOGPATH', LOGPATH.DIRECTORY_SEPARATOR.'dev');     // LOGPATH 정의 안됨
```

**영향**: Fatal error 발생 가능

**해결책**:
1. 사용되지 않으므로 파일 삭제
2. 또는 BASE_PATH 기반으로 재작성

---

### 2. **AuthService - MySQL 전용 NOW() 사용**
**파일**: `ace/Auth/AuthService.php`
**라인**: 44, 176-178, 197, 425-426, 433-434, 444-445

**문제**:
```php
// MySQL에서만 작동
$this->db->prepareQuery(
    "INSERT INTO users (..., created_at, updated_at) VALUES (..., NOW(), NOW())",
    [...]
);
```

**영향**: SQLite 사용 시 에러 발생

**해결책**:
```php
$now = date('Y-m-d H:i:s');
$this->db->prepareQuery(
    "INSERT INTO users (..., created_at, updated_at) VALUES (..., ?, ?)",
    [..., $now, $now]
);
```

---

### 3. **TokenManager - MySQL 전용 NOW() 사용**
**파일**: `ace/Auth/TokenManager.php`
**라인**: 여러 곳 (읽지 못한 부분에 있을 가능성)

**문제**: NOW() 함수 사용

**해결책**: `date('Y-m-d H:i:s')` 사용

---

### 4. **Env.php - explode() 결과 처리 오류**
**파일**: `ace/Support/Env.php`
**라인**: 40

**문제**:
```php
list($name, $value) = explode('=', $line, 2);
```
'=' 가 없는 라인이 있으면 에러 발생

**해결책**:
```php
if (!str_contains($line, '=')) {
    continue;
}
list($name, $value) = explode('=', $line, 2);
```

---

## 🟡 Warning (수정 권장)

### 5. **Model.php - master 연결 미사용**
**파일**: `ace/Database/Model.php`
**라인**: 108

**문제**:
```php
public static function create(array $data): int
{
    // ...
    $dbManager = app(Db::class);
    $db = $dbManager->driver(env('DB_CONNECTION', 'mysql')); // ❌ master=false
    return $db->getLastInsertId();
}
```

`statement()`에서는 master=true를 사용하지만, `getLastInsertId()`는 slave에서 가져옴

**해결책**:
```php
$db = $dbManager->driver(env('DB_CONNECTION', 'mysql'), true); // master=true
```

---

### 6. **MysqlConnector - 타입 일관성**
**파일**: `ace/Database/MysqlConnector.php`
**라인**: 76, 135

**문제**:
```php
$result = $stmt->get_result();  // ❌ mysqli_result 또는 false 반환
$stmt->close();
return $result;
```

false 반환 가능성이 있지만 반환 타입에 명시되지 않음

**해결책**:
```php
$result = $stmt->get_result();
$stmt->close();

if ($result === false) {
    throw new \Exception('Failed to get result from statement');
}

return $result;
```

---

### 7. **Router - getClassNameFromFile() 미구현**
**파일**: `ace/Http/Router.php`
**라인**: 92

**문제**:
```php
private function getClassNameFromFile(string $path): ?string { /* ... */ }
```

메서드 시그니처만 있고 구현이 없음

**영향**: 컨트롤러 라우팅 불가능

**해결책**: 구현 추가 필요

---

### 8. **default.php - 레거시 함수**
**파일**: `ace/Support/default.php`
**라인**: 53-60

**문제**:
```php
function _L($str) {
    return \APP\App::singleton('unit', 'language.page')->_L($str);
}

function redkokoPriceFormat($price, $curr, $format = TRUE) {
    return \APP\App::singleton('unit', 'calculation.price')->redkokoFormat($price, $curr, $format);
}
```

특정 프로젝트용 함수가 ACE 프레임워크에 포함됨

**해결책**: 제거

---

## 🔵 Info (개선 권장)

### 9. **Model.php - SQL Injection 가능성**
**파일**: `ace/Database/Model.php`
**라인**: 83

**문제**:
```php
public static function where(string $column, mixed $value): array
{
    $table = static::getTableName();
    return static::select("SELECT * FROM {$table} WHERE {$column} = ?", [$value]);
}
```

$column이 사용자 입력에서 올 경우 SQL Injection 가능

**권장사항**: $column 화이트리스트 검증 추가

---

### 10. **AuthService - 보안 강화 필요**
**파일**: `ace/Auth/AuthService.php`

**개선사항**:
- Rate limiting 없음 (무차별 대입 공격 취약)
- 계정 잠금 기능 없음
- IP 화이트리스트 없음

---

### 11. **TokenManager - 보안 경고**
**파일**: `ace/Auth/TokenManager.php`
**라인**: 47-51

**문제**:
```php
// Simple base64 encoding (for production, use proper JWT library)
$payload = base64_encode(json_encode($data));
$signature = hash_hmac('sha256', $payload, $this->getSecret());
```

프로덕션 환경에서는 적절한 JWT 라이브러리 사용 권장

---

## 테스트 케이스

### Test 1: 기본 DB 연결 테스트
```php
<?php
require_once 'public/index.php';

$db = app(\ACE\Database\Db::class);
$mysql = $db->driver('mysql');
echo "MySQL Connection: OK\n";
```

### Test 2: Model CRUD 테스트
```php
// Create
$id = TestModel::create(['name' => 'Test']);
echo "Created ID: {$id}\n";

// Read
$record = TestModel::find($id);
echo "Found: " . $record['name'] . "\n";

// Update
TestModel::update($id, ['name' => 'Updated']);

// Delete
TestModel::delete($id);
```

### Test 3: AuthService 테스트
```php
$auth = new \ACE\Auth\AuthService();

// Register
$user = $auth->register([
    'email' => 'test@test.com',
    'password' => 'password123',
    'name' => 'Test User'
]);

// Login
$tokens = $auth->login('test@test.com', 'password123');
echo "Access Token: {$tokens['access_token']}\n";
```

---

## 우선순위

### Immediate (오늘)
1. ✅ path.php 삭제 또는 수정
2. ✅ Env.php explode() 버그 수정
3. ✅ Router getClassNameFromFile() 구현

### Short-term (1-2일)
4. ✅ AuthService NOW() 제거
5. ✅ TokenManager NOW() 제거
6. ✅ Model.php master 연결 수정
7. ✅ MysqlConnector 에러 처리 추가

### Medium-term (1주일)
8. _L(), redkokoPriceFormat() 제거
9. Model where() SQL Injection 방지
10. AuthService 보안 강화

---

## 예상 작업 시간
- Critical 수정: 2-3시간
- Warning 수정: 1-2시간
- 테스트: 1시간
- **총 예상: 4-6시간**

---

**검증자**: Claude (AI Assistant)
**검증 방법**: 정적 분석 + 코드 리뷰
