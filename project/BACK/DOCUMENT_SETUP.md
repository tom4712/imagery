# 문서 기능 적용 안내

문서 목록, 생성, 편집 저장, 삭제는 모두 `_common.php`의 `IMG_STORAGE_ROOT`를 기준으로 동작합니다.

PHP 8/XAMPP에서는 경로를 CP949로 변환하지 않습니다. 파일과 폴더명은 UTF-8로 저장해야 하며, 이 수정본의 `_common.php`를 반드시 함께 적용하세요.

기존 E 드라이브를 계속 사용한다면 추가 설정이 필요 없습니다. 원본 저장소가 다른 위치라면, 그누보드 `config.php`에서 프로젝트 공통 파일보다 먼저 다음처럼 지정하세요.

```php
define('IMG_STORAGE_ROOT', 'D:\\imagery-data');
```

저장소에는 아래 구조가 있어야 합니다. 촬영일을 추가할 때 `문서` 폴더는 자동 생성됩니다.

```text
{IMG_STORAGE_ROOT}\{사업명}\date\{YYYY-MM-DD}\문서
```

`촬영기록부` 생성에는 `project/base/flight_log.xlsx`가 필요합니다. `코스별검사표` 생성에는 별도 양식인 `project/base/course_inspect.xlsx`를 업로드해야 합니다. 이번 전달본에는 후자가 없었으므로, 양식 없이 임의로 생성하지 않도록 명확한 오류 메시지를 표시하게 했습니다.
