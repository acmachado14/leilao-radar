import { useCallback, useEffect, useMemo, useRef, useState, type SetStateAction } from 'react';
import {
  ActivityIndicator,
  Alert,
  Dimensions,
  FlatList,
  Image,
  Linking,
  Pressable,
  RefreshControl,
  ScrollView,
  Share,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Gesture, GestureDetector } from 'react-native-gesture-handler';
import Animated, { runOnJS, useAnimatedStyle, useSharedValue, withTiming } from 'react-native-reanimated';
import { SafeAreaView } from 'react-native-safe-area-context';
import { fetchMe, fetchScreen, postAction } from '../api';
import { setToken } from '../auth';
import { configurePurchases, purchase, restore } from '../purchases';
import { useAppTheme, type ThemeColors } from '../theme';
import { RenderNode } from './registry';
import type { ScreenDocument, SduiAction, SduiNode } from './types';

type Frame = { name: string; params: Record<string, string>; document?: ScreenDocument };

const STACK_ROOTS = new Set(['catalog', 'my_lots', 'alerts', 'paywall', 'account', 'login', 'register']);
const EDGE_WIDTH = 24;
const POP_THRESHOLD = 0.35;

const TAB_ICONS: Record<string, { outline: keyof typeof Ionicons.glyphMap; filled: keyof typeof Ionicons.glyphMap }> = {
  catalog: { outline: 'car-sport-outline', filled: 'car-sport' },
  my_lots: { outline: 'heart-outline', filled: 'heart' },
  alerts: { outline: 'notifications-outline', filled: 'notifications' },
  paywall: { outline: 'diamond-outline', filled: 'diamond' },
  account: { outline: 'person-circle-outline', filled: 'person-circle' },
};

const brandMark = require('../../assets/icon.png');

const BACK_LABELS: Record<string, string> = {
  catalog: 'Ofertas',
  my_lots: 'Meus',
  alerts: 'Recortes',
  paywall: 'Planos',
  account: 'Conta',
};

export function ScreenRenderer({ initial = 'catalog' }: { initial?: string }) {
  const { colors } = useAppTheme();
  const styles = useMemo(() => createScreenStyles(colors), [colors]);
  const screenWidth = Dimensions.get('window').width;
  const [screenName, setScreenName] = useState(initial);
  const screenRef = useRef(initial);
  const [params, setParams] = useState<Record<string, string>>({});
  const [document, setDocument] = useState<ScreenDocument | null>(null);
  const [form, setFormState] = useState<Record<string, unknown>>({});
  const formRef = useRef<Record<string, unknown>>({});

  function setForm(next: SetStateAction<Record<string, unknown>>) {
    setFormState((current) => {
      const value = typeof next === 'function' ? next(current) : next;
      formRef.current = value;
      return value;
    });
  }
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [stack, setStack] = useState<Frame[]>([{ name: initial, params: {} }]);
  const stackRef = useRef<Frame[]>([{ name: initial, params: {} }]);
  const translateX = useSharedValue(0);

  function writeStack(next: Frame[]) {
    stackRef.current = next;
    setStack(next);
  }

  function patchTopFrame(patch: Partial<Frame>) {
    const current = stackRef.current;
    if (current.length === 0) {
      return;
    }
    const last = current[current.length - 1];
    writeStack([...current.slice(0, -1), { ...last, ...patch }]);
  }

  function applyDocumentToStack(doc: ScreenDocument, frameParams: Record<string, string>) {
    patchTopFrame({ document: doc, name: doc.screen, params: frameParams });
  }

  const load = useCallback(async (name: string, nextParams: Record<string, string> = {}) => {
    setError(null);
    const next = await fetchScreen(name, nextParams);
    setDocument(next);
    setScreenName(next.screen);
    screenRef.current = next.screen;
    setParams(nextParams);
    setForm(collectDefaults(next.components ?? []));
    applyDocumentToStack(next, nextParams);
    return next;
  }, []);

  useEffect(() => {
    fetchMe()
      .then((me) => {
        if (me?.id) {
          return configurePurchases(String(me.id));
        }
      })
      .catch(() => undefined);
  }, []);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    fetchScreen(initial)
      .then((next) => {
        if (!cancelled) {
          setDocument(next);
          setScreenName(next.screen);
          screenRef.current = next.screen;
          setForm(collectDefaults(next.components ?? []));
          applyDocumentToStack(next, {});
        }
      })
      .catch((err: Error) => {
        if (!cancelled) setError(err.message);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [initial]);

  const pollStatus = String(document?.meta?.status ?? '');
  const pollLote = String(document?.meta?.lote_id ?? '');
  const pollSpec = document?.meta?.poll;

  useEffect(() => {
    if (pollStatus !== 'pending' || !pollSpec || typeof pollSpec !== 'object') {
      return;
    }
    const spec = pollSpec as { screen?: string; params?: Record<string, string>; interval_ms?: number };
    if (!spec.screen) {
      return;
    }
    const timer = setInterval(() => {
      fetchScreen(spec.screen ?? 'evaluation', spec.params ?? {})
        .then((next) => {
          if (String(next.meta?.status ?? '') === 'pending') {
            return;
          }
          setDocument(next);
          setScreenName(next.screen);
          screenRef.current = next.screen;
          patchTopFrame({ document: next });
        })
        .catch(() => undefined);
    }, Number(spec.interval_ms) || 2000);
    return () => clearInterval(timer);
  }, [pollStatus, pollLote, pollSpec]);

  function rememberFrame(name: string, nextParams: Record<string, string>) {
    if (STACK_ROOTS.has(name)) {
      writeStack([{ name, params: nextParams }]);
      return;
    }
    const current = stackRef.current;
    const last = current[current.length - 1];
    if (last?.name === name) {
      writeStack([...current.slice(0, -1), { name, params: nextParams, document: last.document }]);
      return;
    }
    writeStack([...current, { name, params: nextParams }]);
  }

  function restoreFromFrame(frame: Frame) {
    if (!frame.document) {
      return false;
    }
    setDocument(frame.document);
    setScreenName(frame.document.screen);
    screenRef.current = frame.document.screen;
    setParams(frame.params);
    setForm(collectDefaults(frame.document.components ?? []));
    setError(null);
    translateX.value = 0;
    return true;
  }

  async function applyDocument(next: ScreenDocument) {
    const previous = screenRef.current;
    setDocument(next);
    setScreenName(next.screen);
    screenRef.current = next.screen;
    if (previous !== next.screen) {
      setForm(collectDefaults(next.components ?? []));
    }
    if (STACK_ROOTS.has(next.screen)) {
      writeStack([{ name: next.screen, params: {}, document: next }]);
    } else {
      const last = stackRef.current[stackRef.current.length - 1];
      if (last?.name !== next.screen) {
        writeStack([...stackRef.current, { name: next.screen, params: {}, document: next }]);
      } else {
        patchTopFrame({ document: next, name: next.screen });
      }
    }
    if (next.token) {
      const me = await fetchMe();
      if (me?.id) {
        await configurePurchases(me.id);
      }
    }
  }

  const goBack = useCallback(async () => {
    if (stackRef.current.length < 2) {
      return;
    }
    const nextStack = stackRef.current.slice(0, -1);
    const frame = nextStack[nextStack.length - 1];
    writeStack(nextStack);
    if (restoreFromFrame(frame)) {
      return;
    }
    setLoading(true);
    try {
      await load(frame.name, frame.params);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Falha ao voltar');
    } finally {
      setLoading(false);
    }
  }, [load]);

  async function handleAction(action: SduiAction) {
    if (action.confirm) {
      const ok = await new Promise<boolean>((resolve) => {
        Alert.alert('Confirmar', action.confirm, [
          { text: 'Cancelar', style: 'cancel', onPress: () => resolve(false) },
          {
            text: action.destructive ? 'Apagar' : 'OK',
            style: action.destructive ? 'destructive' : 'default',
            onPress: () => resolve(true),
          },
        ]);
      });
      if (!ok) {
        return;
      }
    }

    if (action.type === 'navigate' && action.screen) {
      const nextParams = action.params ?? {};
      rememberFrame(action.screen, nextParams);
      setLoading(true);
      try {
        await load(action.screen, nextParams);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Falha ao abrir tela');
      } finally {
        setLoading(false);
      }
      return;
    }

    if (action.type === 'reload_screen') {
      const nextParams = {
        ...params,
        ...(action.params ?? {}),
        ...serializeForm(formRef.current),
      };
      const current = stackRef.current;
      const last = current[current.length - 1];
      if (last) {
        writeStack([...current.slice(0, -1), { name: last.name, params: nextParams }]);
      }
      setRefreshing(true);
      try {
        await load(action.screen ?? screenName, nextParams);
      } finally {
        setRefreshing(false);
      }
      return;
    }

    if (action.type === 'open_url' && action.url) {
      await Linking.openURL(action.url);
      return;
    }

    if (action.type === 'share' && action.url) {
      try {
        await Share.share({ url: action.url, message: action.url });
      } catch {
        // User dismissed the sheet.
      }
      return;
    }

    if (action.type === 'logout') {
      const next = await postAction('logout');
      await setToken(null);
      await applyDocument(next);
      return;
    }

    if (action.type === 'purchase') {
      const id = action.params?.id;
      if (!id) {
        return;
      }
      try {
        const result = await purchase(id);
        if (result === 'cancelled') {
          return;
        }
        if (result === 'unavailable') {
          Alert.alert(
            'Assinatura indisponível',
            'Os planos ainda não chegaram da App Store neste iPhone. Tente Restaurar compras ou tente de novo em instantes.',
          );
          return;
        }
        rememberFrame('account', {});
        await load('account');
      } catch (err) {
        Alert.alert(
          'Não foi possível assinar',
          'A compra acontece na App Store. Tente de novo em alguns instantes ou toque em Restaurar compras.',
        );
      }
      return;
    }

    if (action.type === 'restore') {
      try {
        await restore();
        rememberFrame('account', {});
        await load('account');
      } catch {
        Alert.alert('Restaurar compras', 'Nenhuma compra foi encontrada neste Apple ID.');
      }
      return;
    }

    if (action.type === 'submit' && action.action) {
      setLoading(true);
      try {
        const next = await postAction(action.action, { ...formRef.current, ...(action.params ?? {}) });
        await applyDocument(next);
        const stayedOnAuth = next.meta?.guest === true || next.screen === 'login' || next.screen === 'register';
        if (action.then && !stayedOnAuth) {
          await handleAction(action.then);
        }
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Falha na ação');
      } finally {
        setLoading(false);
      }
    }
  }

  const canGoBack = stack.length > 1;
  const previous = canGoBack ? stack[stack.length - 2] : null;
  const underlayDocument = previous?.document ?? null;

  const finishPop = useCallback(() => {
    void goBack();
  }, [goBack]);

  const edgeSwipeGesture = useMemo(() => {
    return Gesture.Pan()
      .activeOffsetX(8)
      .failOffsetY([-16, 16])
      .onUpdate((event) => {
        if (event.translationX > 0) {
          translateX.value = Math.min(event.translationX, screenWidth);
        }
      })
      .onEnd((event) => {
        const shouldPop =
          event.translationX > screenWidth * POP_THRESHOLD || (event.velocityX > 800 && event.translationX > 40);
        if (shouldPop) {
          translateX.value = withTiming(screenWidth, { duration: 180 }, () => {
            runOnJS(finishPop)();
          });
        } else {
          translateX.value = withTiming(0, { duration: 180 });
        }
      });
  }, [finishPop, screenWidth, translateX]);

  const animatedScreenStyle = useAnimatedStyle(() => ({
    transform: [{ translateX: translateX.value }],
  }));

  const animatedDimStyle = useAnimatedStyle(() => ({
    opacity: Math.min(translateX.value / screenWidth, 1) * 0.18,
  }));

  if (loading && !document) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={colors.accent} />
      </View>
    );
  }

  if (error && !document) {
    return (
      <View style={styles.center}>
        <Text style={styles.error}>{error}</Text>
        <Pressable onPress={() => load(initial)} style={styles.retry}>
          <Text style={styles.retryLabel}>Tentar de novo</Text>
        </Pressable>
      </View>
    );
  }

  if (!document) {
    return null;
  }

  const tabs = document.tabs ?? [];

  function renderScreenBody(activeDocument: ScreenDocument, interactive: boolean) {
    const components = activeDocument.components ?? [];
    const listIndex = components.findIndex((node) => node.type === 'List');
    const headerNodes = listIndex >= 0 ? components.slice(0, listIndex) : components;
    const rows = listIndex >= 0 ? (components[listIndex]?.children ?? []) : [];
    const footerNodes = listIndex >= 0 ? components.slice(listIndex + 1) : [];
    const hasList = rows.length > 0;

    function renderBlock(node: SduiNode, index: number) {
      return (
        <View key={node.id ?? `${node.type}-${index}`}>
          <RenderNode
            node={node}
            form={form}
            setForm={setForm}
            onAction={interactive ? handleAction : () => undefined}
          />
        </View>
      );
    }

    const refreshControl = interactive ? (
      <RefreshControl
        tintColor={colors.accent}
        refreshing={refreshing}
        onRefresh={async () => {
          setRefreshing(true);
          try {
            await load(screenName, params);
          } finally {
            setRefreshing(false);
          }
        }}
      />
    ) : undefined;

    if (hasList) {
      return (
        <>
          {headerNodes.length ? <View style={styles.content}>{headerNodes.map(renderBlock)}</View> : null}
          <FlatList
            data={rows}
            keyExtractor={(item, index) => item.id ?? `${item.type}-${index}`}
            renderItem={({ item, index }) => <View style={styles.row}>{renderBlock(item, index)}</View>}
            ListFooterComponent={
              footerNodes.length ? <View style={styles.footer}>{footerNodes.map(renderBlock)}</View> : null
            }
            contentContainerStyle={styles.listContent}
            refreshControl={refreshControl}
            scrollEnabled={interactive}
            initialNumToRender={12}
            windowSize={7}
            keyboardShouldPersistTaps="handled"
            keyboardDismissMode="interactive"
            automaticallyAdjustKeyboardInsets
          />
        </>
      );
    }

    return (
      <ScrollView
        key={activeDocument.screen}
        keyboardShouldPersistTaps="handled"
        keyboardDismissMode="interactive"
        automaticallyAdjustKeyboardInsets
        contentContainerStyle={styles.formContent}
        refreshControl={refreshControl}
        scrollEnabled={interactive}
      >
        {headerNodes.map(renderBlock)}
        {footerNodes.map(renderBlock)}
      </ScrollView>
    );
  }

  function renderNavBar(activeDocument: ScreenDocument, showBack: boolean, backTarget: Frame | null) {
    return (
      <View style={[styles.navBar, showBack && styles.navBarCompact]}>
        {showBack ? (
          <Pressable onPress={goBack} hitSlop={10} style={styles.backButton}>
            <Text style={styles.backLabel}>‹ {BACK_LABELS[backTarget?.name ?? ''] ?? 'Voltar'}</Text>
          </Pressable>
        ) : (
          <Image source={brandMark} style={styles.brandMark} />
        )}
        <Text style={[styles.navTitle, showBack && styles.navTitleCompact]} numberOfLines={1}>
          {activeDocument.title ?? 'VerifyRadar'}
        </Text>
        {showBack ? <View style={styles.backSpacer} /> : <View style={styles.brandMark} />}
      </View>
    );
  }

  return (
    <SafeAreaView style={styles.safe} edges={['top', 'bottom']}>
      <View style={styles.stackHost}>
        {underlayDocument ? (
          <View style={styles.underlay} pointerEvents="none">
            {renderNavBar(underlayDocument, false, null)}
            <View style={styles.underlayBody}>{renderScreenBody(underlayDocument, false)}</View>
            <Animated.View style={[styles.swipeDim, animatedDimStyle]} />
          </View>
        ) : null}
        <Animated.View style={[styles.stackLayer, animatedScreenStyle]}>
            {renderNavBar(document, canGoBack, previous)}
            <View style={styles.stackBody}>{renderScreenBody(document, true)}</View>
            {canGoBack ? (
              <GestureDetector gesture={edgeSwipeGesture}>
                <View style={styles.edgeCapture} />
              </GestureDetector>
            ) : null}
          </Animated.View>
      </View>
      {tabs.length > 0 ? (
        <View style={styles.tabs}>
          {tabs.map((tab) => {
            const icons = TAB_ICONS[tab.id];
            const iconName = tab.active ? icons?.filled : icons?.outline;
            return (
              <Pressable
                key={tab.id}
                onPress={() => handleAction({ type: 'navigate', screen: tab.screen })}
                style={[styles.tab, tab.active && styles.tabSelected]}
              >
                {iconName ? (
                  <Ionicons name={iconName} size={22} color={tab.active ? colors.accent : colors.dim} />
                ) : null}
                <Text style={[styles.tabLabel, tab.active && styles.tabActive]}>{tab.label}</Text>
              </Pressable>
            );
          })}
        </View>
      ) : null}
    </SafeAreaView>
  );
}

function collectDefaults(nodes: SduiNode[]): Record<string, unknown> {
  const values: Record<string, unknown> = {};
  for (const node of nodes) {
    if (node.type === 'FilterSheet') {
      continue;
    }
    const props = node.props ?? {};
    if (typeof props.name === 'string' && props.value !== undefined) {
      values[props.name] = props.value;
    }
    if (node.children?.length) {
      Object.assign(values, collectDefaults(node.children));
    }
  }
  return values;
}

function serializeForm(form: Record<string, unknown>): Record<string, string> {
  const values: Record<string, string> = {};
  for (const [key, value] of Object.entries(form)) {
    if (value === undefined || value === null) {
      continue;
    }
    values[key] = typeof value === 'boolean' ? (value ? '1' : '0') : String(value);
  }
  return values;
}

function createScreenStyles(colors: ThemeColors) {
  return StyleSheet.create({
    safe: { flex: 1, backgroundColor: colors.bg },
    stackHost: { flex: 1, overflow: 'hidden', backgroundColor: colors.bg },
    stackLayer: { flex: 1, backgroundColor: colors.bg },
    stackBody: { flex: 1 },
    underlay: { ...StyleSheet.absoluteFill, backgroundColor: colors.bg },
    underlayBody: { flex: 1 },
    swipeDim: { ...StyleSheet.absoluteFill, backgroundColor: '#000' },
    edgeCapture: {
      position: 'absolute',
      left: 0,
      top: 0,
      bottom: 0,
      width: EDGE_WIDTH,
      zIndex: 2,
    },
    navBar: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 12,
      paddingHorizontal: 20,
      paddingTop: 8,
      paddingBottom: 10,
    },
    navBarCompact: {
      minHeight: 44,
      paddingBottom: 8,
      gap: 0,
    },
    brandMark: { width: 36, height: 36, borderRadius: 9 },
    backButton: { minWidth: 88 },
    backSpacer: { minWidth: 88 },
    backLabel: { color: colors.accent, fontSize: 17 },
    navTitle: {
      color: colors.text,
      fontSize: 28,
      fontWeight: '700',
    },
    navTitleCompact: {
      flex: 1,
      fontSize: 17,
      textAlign: 'center',
    },
    content: { paddingHorizontal: 20, paddingTop: 8, gap: 12, paddingBottom: 8 },
    footer: { paddingHorizontal: 20, paddingBottom: 32, gap: 12 },
    listContent: { paddingBottom: 24 },
    formContent: { paddingHorizontal: 20, paddingTop: 8, paddingBottom: 40, gap: 12, flexGrow: 1 },
    row: { paddingHorizontal: 20 },
    center: { flex: 1, backgroundColor: colors.bg, alignItems: 'center', justifyContent: 'center', padding: 24 },
    error: { color: colors.danger, textAlign: 'center', marginBottom: 12 },
    retry: { backgroundColor: colors.accent, borderRadius: 12, paddingHorizontal: 16, paddingVertical: 10 },
    retryLabel: { color: colors.onAccent, fontWeight: '700' },
    tabs: {
      flexDirection: 'row',
      borderTopWidth: 1,
      borderTopColor: colors.border,
      backgroundColor: colors.card,
      paddingTop: 6,
      paddingBottom: 4,
    },
    tab: { flex: 1, paddingVertical: 6, alignItems: 'center', gap: 3, borderRadius: 12 },
    tabSelected: { backgroundColor: colors.accentSoft },
    tabLabel: { color: colors.dim, fontSize: 12, fontWeight: '700' },
    tabActive: { color: colors.accent },
  });
}
