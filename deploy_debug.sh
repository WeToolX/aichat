#!/usr/bin/env bash
set -euo pipefail

HOST="${DEPLOY_HOST:-http://122.51.187.121:7788}"
ENDPOINT="${HOST%/}/debug/deploy/pull"
TOKEN="${DEPLOY_WEBHOOK_TOKEN:-${X_DEPLOY_TOKEN:-}}"
REMOTE="${DEPLOY_GIT_REMOTE:-origin}"
COMMIT_MESSAGE=""
SKIP_COMMIT="false"

usage() {
  cat <<'EOF'
用法:
  DEPLOY_WEBHOOK_TOKEN=xxx bash deploy_debug.sh [-m "提交说明"] [--skip-commit]

说明:
  1. 如果工作区有变更:
     - 默认会要求提供 -m 提交说明
     - 提供后会执行 git add -A && git commit -m
  2. 然后执行 git push <remote> <current-branch>
  3. 最后调用服务器调试部署接口 /debug/deploy/pull

可选环境变量:
  DEPLOY_WEBHOOK_TOKEN   部署接口密钥，必填
  DEPLOY_HOST            部署接口地址，默认 http://122.51.187.121:7788
  DEPLOY_GIT_REMOTE      Git 远端名，默认 origin

参数:
  -m, --message          Git 提交说明
  --skip-commit          有未提交改动时不自动提交，直接报错退出
  -h, --help             显示帮助
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    -m|--message)
      if [[ $# -lt 2 ]]; then
        echo "缺少提交说明" >&2
        exit 1
      fi
      COMMIT_MESSAGE="$2"
      shift 2
      ;;
    --skip-commit)
      SKIP_COMMIT="true"
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "未知参数: $1" >&2
      usage
      exit 1
      ;;
  esac
done

if [[ -z "$TOKEN" ]]; then
  echo "缺少 DEPLOY_WEBHOOK_TOKEN 环境变量" >&2
  exit 1
fi

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "当前目录不是 Git 仓库" >&2
  exit 1
fi

BRANCH="$(git branch --show-current)"
if [[ -z "$BRANCH" ]]; then
  echo "无法识别当前分支" >&2
  exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
  if [[ "$SKIP_COMMIT" == "true" ]]; then
    echo "工作区存在未提交改动，已按 --skip-commit 终止" >&2
    exit 1
  fi

  if [[ -z "$COMMIT_MESSAGE" ]]; then
    echo "工作区存在未提交改动，请通过 -m 传入提交说明" >&2
    exit 1
  fi

  echo "提交本地改动..."
  git add -A
  git commit -m "$COMMIT_MESSAGE"
fi

echo "推送分支 ${BRANCH} 到 ${REMOTE}..."
git push "$REMOTE" "$BRANCH"

echo "调用部署接口 ${ENDPOINT} ..."
TMP_RESPONSE="$(mktemp)"
HTTP_STATUS="$(
  curl --silent --show-error \
    -o "${TMP_RESPONSE}" \
    -w "%{http_code}" \
    -X POST \
    -H "X-Deploy-Token: ${TOKEN}" \
    "${ENDPOINT}"
)"

cat "${TMP_RESPONSE}"
echo
rm -f "${TMP_RESPONSE}"

if [[ "${HTTP_STATUS}" -lt 200 || "${HTTP_STATUS}" -ge 300 ]]; then
  echo "部署接口调用失败，HTTP 状态码: ${HTTP_STATUS}" >&2
  exit 1
fi

echo
echo "部署请求已发送"
