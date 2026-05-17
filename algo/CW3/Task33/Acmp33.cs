using System;
using System.Collections.Generic;
using System.Globalization;
using System.IO;
using System.Text;

namespace CW3.Task33.Acmp;

/// <summary>
/// Задача 33. На метро или пешком? — самодостаточный файл для отправки решения.
///
/// Целевая платформа: .NET 8 (используется встроенный PriorityQueue&lt;TElement, TPriority&gt;).
/// Скопировать всё содержимое файла (включая using-и и namespace) в окно отправки решения.
///
/// Формат ввода (stdin):
///   walkSpeed metroSpeed
///   N
///   x1 y1
///   ...
///   xN yN
///   u1 v1
///   u2 v2
///   ...
///   0 0
///   ax ay
///   bx by
/// где walkSpeed и metroSpeed — скорости (0.5 ≤ ... ≤ 10000), N (2 ≤ N ≤ 200) —
/// количество станций, (xi, yi) — координаты i-й станции, пары (ui, vi) —
/// соединения станций (нумерация с 1), список завершается парой "0 0".
/// (ax, ay) и (bx, by) — координаты точек A и B.
///
/// Формат вывода (stdout):
///   Первая строка — минимальное время с точностью до 10^-6.
///   Вторая строка — количество станций маршрута и сами номера через пробел.
///
/// Решение: строим граф «станции + A + B». Метро соединяет станции по списку
/// (вес = расстояние / metroSpeed). Пешие рёбра — между всеми парами вершин
/// (A↔B, A/B↔каждая станция, каждая пара станций) с весом расстояние / walkSpeed.
/// Пешие переходы между станциями обязательно нужны: оптимум может выглядеть как
/// A → walk → S1 → metro → S2 → walk → S3 → metro → S4 → walk → B, где шаг
/// S2 → walk → S3 не сводится к прямому пешему A↔B. Дальше — Дейкстра от A до B
/// с восстановлением пути.
/// </summary>
internal static class Acmp33
{
    private static void Main()
    {
        var inv = CultureInfo.InvariantCulture;
        var tokens = Console.In.ReadToEnd()
            .Split(new[] { ' ', '\t', '\r', '\n' }, StringSplitOptions.RemoveEmptyEntries);

        int idx = 0;
        double walkSpeed = double.Parse(tokens[idx++], inv);
        double metroSpeed = double.Parse(tokens[idx++], inv);

        int n = int.Parse(tokens[idx++], inv);
        double[][] stations = new double[n][];
        for (int i = 0; i < n; i++)
        {
            stations[i] = new double[]
            {
                double.Parse(tokens[idx++], inv),
                double.Parse(tokens[idx++], inv)
            };
        }

        // Соединения станций до пары "0 0"
        var connections = new List<int[]>();
        while (true)
        {
            int u = int.Parse(tokens[idx++], inv);
            int v = int.Parse(tokens[idx++], inv);
            if (u == 0 && v == 0) break;
            connections.Add(new[] { u, v });
        }

        double[] a = { double.Parse(tokens[idx++], inv), double.Parse(tokens[idx++], inv) };
        double[] b = { double.Parse(tokens[idx++], inv), double.Parse(tokens[idx++], inv) };

        int nodeA = n;
        int nodeB = n + 1;
        int total = n + 2;

        var graph = new List<(int to, double weight)>[total];
        for (int i = 0; i < total; i++)
            graph[i] = new List<(int, double)>();

        void AddEdge(int u, int v, double w)
        {
            graph[u].Add((v, w));
            graph[v].Add((u, w));
        }

        foreach (var c in connections)
        {
            int u = c[0] - 1, v = c[1] - 1;
            double w = Distance(stations[u], stations[v]) / metroSpeed;
            AddEdge(u, v, w);
        }

        for (int i = 0; i < n; i++)
        {
            AddEdge(nodeA, i, Distance(a, stations[i]) / walkSpeed);
            AddEdge(nodeB, i, Distance(b, stations[i]) / walkSpeed);
        }
        AddEdge(nodeA, nodeB, Distance(a, b) / walkSpeed);

        // Пешие рёбра между всеми парами станций
        for (int i = 0; i < n; i++)
            for (int j = i + 1; j < n; j++)
                AddEdge(i, j, Distance(stations[i], stations[j]) / walkSpeed);

        // Алгоритм Дейкстры от A
        double[] dist = new double[total];
        int[] prev = new int[total];
        bool[] visited = new bool[total];
        for (int i = 0; i < total; i++)
        {
            dist[i] = double.MaxValue;
            prev[i] = -1;
        }
        dist[nodeA] = 0.0;

        var pq = new PriorityQueue<int, double>();
        pq.Enqueue(nodeA, 0.0);
        while (pq.Count > 0)
        {
            int u = pq.Dequeue();
            if (visited[u]) continue;
            visited[u] = true;

            foreach (var (to, w) in graph[u])
            {
                double nd = dist[u] + w;
                if (nd < dist[to])
                {
                    dist[to] = nd;
                    prev[to] = u;
                    pq.Enqueue(to, nd);
                }
            }
        }

        // Восстановление маршрута и выделение из него номеров станций
        var stationPath = new List<int>();
        int cur = nodeB;
        while (cur != -1)
        {
            if (cur < n)
                stationPath.Add(cur + 1);
            cur = prev[cur];
        }
        stationPath.Reverse();

        var output = new StringBuilder();
        output.AppendLine(dist[nodeB].ToString("F7", inv));
        output.Append(stationPath.Count);
        foreach (var s in stationPath)
            output.Append(' ').Append(s);
        output.AppendLine();
        Console.Write(output.ToString());
    }

    private static double Distance(double[] p, double[] q)
    {
        double dx = p[0] - q[0];
        double dy = p[1] - q[1];
        return Math.Sqrt(dx * dx + dy * dy);
    }
}
