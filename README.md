# 救护车监管平台

一个基于 PHP + MySQL + Docker 的救护车监管全栈应用，包含前台态势看板和后台调度管理。

## 核心特点

- 前台监管看板：展示车辆在线数、进行中急救事件、未处理告警和车辆状态。
- 后台管理：支持新增急救事件、维护救护车状态和当前位置。
- 账号登录：区分管理员、调度员等后台使用角色。
- 数据初始化：内置车辆、事件、告警和测试账号数据。
- Docker 部署：通过 `docker compose` 启动 PHP Web 服务和 MySQL 数据库。

## 技术选型

- 后端：PHP 8.2、PDO、原生 MVC 结构
- 前端：HTML、CSS、少量服务端模板渲染
- 数据库：MySQL 8.0
- Web 服务：Apache
- 部署：Docker、Docker Compose

## 目录结构

```text
.
├── public/              # Web 入口和静态资源
├── src/                 # 控制器、模型、核心类
├── templates/           # 页面模板
├── database/init.sql    # 数据库初始化脚本
├── docker/              # Apache 配置
├── Dockerfile
├── docker-compose.yml
└── README.md
```

## 启动方式

```bash
docker compose up -d --build
```

如果当前环境使用旧版 Compose 命令，也可以执行：

```bash
docker-compose up -d --build
```

启动后访问：

- 前台态势：`http://localhost:8080/`
- 后台登录：`http://localhost:8080/login`
- 数据接口：`http://localhost:8080/api/overview`

停止服务：

```bash
docker compose down
```

或：

```bash
docker-compose down
```

如需重置数据库数据：

```bash
docker compose down -v
docker compose up -d --build
```

旧版 Compose 命令：

```bash
docker-compose down -v
docker-compose up -d --build
```

## 测试账号

| 角色 | 账号 | 密码 | 用途 |
| --- | --- | --- | --- |
| 管理员 | `admin` | `admin123` | 后台管理、监管查看 |
| 调度员 | `dispatcher` | `dispatch123` | 事件录入、车辆状态维护 |

## 数据库连接

- 主机：`localhost`
- 端口：`3307`
- 数据库：`ambulance_platform`
- 用户名：`ambulance_user`
- 密码：`ambulance_pass`
