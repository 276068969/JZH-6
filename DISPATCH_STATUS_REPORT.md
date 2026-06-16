# 急救事件派车状态联动 — 业务规则与变更报告

> 生成日期：2026-06-16  
> 模块：后台管理（急救事件录入 × 车辆监管）  
> 版本：v2.0（状态矩阵 + 派车快照）

---

## 1. 业务背景与问题

原系统中，调度员为急救事件选择 `assigned_ambulance` 派车后：

| 问题 | 影响 |
|------|------|
| 车辆状态一律硬编码为「出车」 | 事件「已受理」但车辆仍为「出车」，未达现场，与业务流程不符 |
| 无明确的状态对应规则 | 调度员凭经验判断，容易出现状态不一致 |
| 无法追溯派车时的原始状态 | 后续审计或故障排查缺少依据 |
| 重复派车和状态不一致常见 | 业务风险，监管数据失真 |

---

## 2. 状态匹配矩阵（核心规则）

### 2.1 矩阵定义

以**事件状态**为行、**车辆状态**为列，定义三种关系：

| 事件状态 | 期望车辆状态（match） | 允许范围（warn） | 理想状态（派车后自动跳转） | 规则描述 |
|---------|---------------------|-----------------|--------------------------|---------|
| `reported` 已上报 | `dispatching` 出车 | dispatching / on_scene / transporting | **dispatching** | 事件刚上报派车，车辆应处于出车前往现场状态 |
| `accepted` 已受理 | `on_scene` 现场处置 / `transporting` 转运中 | dispatching / on_scene / transporting | **on_scene** | 事件已受理意味着调度确认，车辆应已达现场或转运中 |
| `closed` 已关闭 | `standby` 待命 / `maintenance` 检修 | standby / maintenance | **standby** | 事件结案，车辆应回归待命或进入检修 |

### 2.2 一致性级别

| 级别 | 触发条件 | 视觉标识 |
|------|---------|---------|
| ✅ `match` | 车辆状态 ∈ 期望集合 | 绿色行背景 + ✓ 徽章 |
| ⚡ `warn` | 车辆状态 ∉ 期望集合，但 ∈ 允许范围 | 黄色行背景 + ⚡ 徽章 |
| ⚠ `error` | 车辆状态 ∉ 允许集合 | 红色行背景 + ⚠ 徽章 |
| — `none` | 事件未派车 | 灰色徽章 |

### 2.3 关键决策

> **`accepted` 派车后直接跳 `on_scene`**
>
> 业务逻辑：当调度员将事件状态设为「已受理」时，意味着：
> 1. 调度已正式确认派车（非初步上报）
> 2. 现场已接报并出动
> 3. 车辆应处于「现场处置」而非还在「出车」
>
> 因此派车后不再经过 `dispatching`，直接按矩阵 `ideal` 设为 `on_scene`。

---

## 3. 三处落地场景

### 3.1 创建事件（`/admin/cases` POST）

**涉及文件：** [DashboardRepository.php](file:///c:/Users/guich/Desktop/title/JZH-6/src/Models/DashboardRepository.php#L137-L222) `createCaseWithDispatch()`

**流程：**

```
提交事件表单
    │
    ├─► 1. 派车前校验（isAmbulanceAvailable）
    │      ├─ 车辆是否存在？
    │      ├─ 是否检修？→ 不可派
    │      └─ 是否被其他未关闭事件占用？→ 不可派
    │
    ├─► 2. 查矩阵决定目标状态
    │      matrix[caseStatus]['ideal']
    │      reported → dispatching
    │      accepted → on_scene   ← 关键变化！
    │
    ├─► 3. 事务开始
    │      ├─ 保存事件（含快照字段）
    │      │    ├─ dispatch_vehicle_status = 派车前状态
    │      │    └─ dispatched_at = NOW()
    │      ├─ 更新车辆状态 → target（ideal）
    │      └─ 按匹配级别生成提示
    │
    └─► 4. 事务提交 / 回滚
           └─ 通过 Flash（success / warnings / errors）反馈
```

### 3.2 派车提示（前端表单）

**涉及文件：** [admin.php](file:///c:/Users/guich/Desktop/title/JZH-6/templates/admin.php#L293-L423) 前端脚本

- 选择**事件状态**或**救护车**任一变化时，实时计算派车后的一致性
- 按级别显示不同颜色的提示块：
  - 🟢 成功：`✓ 可派车 · 状态匹配`
  - 🟡 警告：`⚡ 可派车 · 状态有偏差`
  - 🔴 错误：不可派车的具体原因
- 显示派车前后状态转换快照：`（派车快照：待命 → 现场处置）`
- 表单下方附带**状态匹配矩阵说明面板**，方便调度员随时查阅规则

### 3.3 事件列表告警

**涉及文件：** [admin.php 事件列表](file:///c:/Users/guich/Desktop/title/JZH-6/templates/admin.php#L202-L290)

- 新增**匹配状态**列，每一行调用 `checkCaseVehicleStatusMatch()`
- 行背景色随级别变化（绿/黄/红），一眼识别异常
- 列出匹配说明和原因
- 在派车列下方展示**派车快照信息**：
  > 派车时：待命 2026-06-16 10:30:00

---

## 4. 派车状态快照

### 4.1 数据库变更

`emergency_cases` 表新增两列：

| 字段 | 类型 | 说明 |
|------|------|------|
| `dispatch_vehicle_status` | VARCHAR(30) NULL | 派车瞬间救护车的**原始状态**快照 |
| `dispatched_at` | TIMESTAMP NULL | 派车时间 |

**初始化数据示例：**

```sql
('CASE20260611001', ..., 'accepted', 'AMB-001', 'dispatching', 15分钟前, NOW())
-- 说明：CASE20260611001 派车时 AMB-001 是「出车」状态，派车时间 15 分钟前
```

### 4.2 快照的用途

1. **审计追溯**：了解派车时车辆的真实状态（待命出车还是强行占用？）
2. **问题排查**：派车后状态异常，可还原初始状态
3. **绩效分析**：统计车辆从「待命」到「现场」平均响应时间
4. **状态对比**：事件列表中快照 vs 当前车辆状态，识别状态是否漂移

---

## 5. 代码变更清单

| 文件 | 变更内容 |
|------|---------|
| `templates/helpers.php` | 新增 `dispatchStatusMatrix()` / `checkCaseVehicleStatusMatch()` / `matchLevelClass()` / `matchLevelText()` |
| `database/init.sql` | `emergency_cases` 表新增快照字段，初始化数据包含快照 |
| `src/Models/DashboardRepository.php` | `createCaseWithDispatch()` 改造：按 `ideal` 设状态、保存快照、返回 `dispatch_info` |
| `templates/admin.php` | 矩阵面板、事件列表匹配列、前端 JS 同步 ideal 规则、快照展示 |
| `public/assets/style.css` | `.matrix-panel`、`.match-badge`、`.snapshot-info`、行高亮、`.status-tag-small` |

---

## 6. 典型场景测试用例

| # | 操作 | 事件状态 | 车辆初始状态 | 预期行为 |
|---|------|---------|-------------|---------|
| T1 | 新增 + 派 AMB-002（待命） | reported | standby | ✓ 匹配，车辆→出车，快照=standby |
| T2 | 新增 + 派 AMB-002（待命） | accepted | standby | ✓ 匹配，车辆→**现场处置**，快照=standby |
| T3 | 新增 + 派 AMB-001（已占用） | reported | dispatching | 🔴 不可派，提示被哪个事件占用 |
| T4 | 新增 + 派 AMB-005（检修） | reported | maintenance | 🔴 不可派，提示检修 |
| T5 | 新增 + 暂不派车 | reported | — | 车辆状态不更新，快照=NULL |
| T6 | 查看事件 CASE20260611001 | accepted | on_scene（当前） | ✓ 匹配，行绿底，快照=dispatching 显示 |
| T7 | 查看事件 CASE20260611002 | reported | on_scene（当前） | ⚡ 偏差，行黄底，期望=出车 |

---

## 7. 前后端规则同源

为避免不一致，**前后端使用同一套矩阵定义**：

```php
// 后端 PHP: templates/helpers.php
function dispatchStatusMatrix(): array {
    return [
        'reported' => [
            'label' => '已上报',
            'expected' => ['dispatching'],
            'allowed'  => ['dispatching', 'on_scene', 'transporting'],
            'ideal'    => 'dispatching',
            'description' => '事件已上报派车，车辆应处于「出车」状态'
        ],
        'accepted' => [
            'label' => '已受理',
            'expected' => ['on_scene', 'transporting'],
            'allowed'  => ['dispatching', 'on_scene', 'transporting'],
            'ideal'    => 'on_scene',  // ← accepted 直接跳 on_scene
            'description' => '事件已受理，车辆应处于「现场处置」或「转运中」'
        ],
        'closed' => [ /* ... */ ]
    ];
}
```

```javascript
// 前端 JS: templates/admin.php <script>
var matrix = {
    'reported': { label:'已上报', expected:['dispatching'], ..., ideal:'dispatching' },
    'accepted': { label:'已受理', expected:['on_scene','transporting'], ..., ideal:'on_scene' },
    'closed':   { /* 完全一致的结构 */ }
};
```

**关键契约：** 任何对矩阵的修改，必须同步修改前后端两处定义。

---

## 8. 附录：车辆状态全集

| 状态码 | 中文 | 是否可派 |
|--------|------|---------|
| `standby` | 待命 | ✅ 是（最佳） |
| `dispatching` | 出车 | ⚠ 是（若无关联事件） |
| `on_scene` | 现场处置 | ⚠ 是（若无关联事件） |
| `transporting` | 转运中 | ⚠ 是（若无关联事件） |
| `maintenance` | 检修 | ❌ 否 |

**可派车判定公式：**

```
dispatchable = 状态 != maintenance AND 无未关闭事件占用
```
