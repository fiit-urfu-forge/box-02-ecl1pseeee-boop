using System;
using System.IO;

namespace CW3.Task8.Acmp
{
    /// <summary>
    /// Задача 8 (acmp #788). Интересная игра с числами — самодостаточный файл.
    ///
    /// Скопировать всё содержимое файла (включая using-и и namespace) в окно отправки.
    /// Совместим с C# 7 (Mono / .NET на acmp).
    ///
    /// Формат ввода (INPUT.TXT):
    ///   N
    ///   A1 B1
    ///   ...
    ///   AN BN
    /// 1 ≤ N ≤ 300000, 1 ≤ Ai, Bi ≤ 3000.
    ///
    /// Формат вывода (OUTPUT.TXT):
    ///   Стоимость игры S1 - S2 при оптимальной игре обоих.
    ///
    /// Идея и сокращение памяти. «Важность» столбца — это (Ai + Bi): забрав его,
    /// первый получает +Ai и одновременно лишает второго -Bi. Поэтому оба игрока в
    /// свой ход берут столбец с наибольшим Ai + Bi. После сортировки по убыванию:
    ///   cost = Σ_{i чётн.}  Ai  −  Σ_{i нечётн.} Bi.
    /// Каждое слагаемое первой суммы можно записать как (A+B) − B и получить:
    ///   cost = Σ_{ходы P1} (A + B) − Σ_все B.
    /// Значит, хранить нужно только массив (A + B) и общую сумму B — это втрое
    /// меньше памяти, чем хранить A, B и индексы отдельно. Лимит acmp ~16 МБ
    /// заставляет также читать ввод побайтово (BufferedStream), а не как одну
    /// строку с последующим Split — иначе строковые объекты съедают весь лимит.
    /// </summary>
    internal static class Acmp8
    {
        private static Stream _input;

        private static int ReadInt()
        {
            int c;
            do { c = _input.ReadByte(); }
            while (c != -1 && (c < '0' || c > '9') && c != '-');

            if (c == -1) return 0;

            bool neg = false;
            if (c == '-') { neg = true; c = _input.ReadByte(); }

            int val = 0;
            while (c >= '0' && c <= '9')
            {
                val = val * 10 + (c - '0');
                c = _input.ReadByte();
            }
            return neg ? -val : val;
        }

        private static void Main()
        {
            _input = new BufferedStream(Console.OpenStandardInput(), 1 << 16);

            int n = ReadInt();
            int[] sums = new int[n];
            long totalB = 0;

            for (int i = 0; i < n; i++)
            {
                int a = ReadInt();
                int b = ReadInt();
                sums[i] = a + b;
                totalB += b;
            }

            // Сортируем по возрастанию (A + B). Первый игрок в свой ход забирает
            // столбец с наибольшей суммой, поэтому ему достаются позиции
            // n-1, n-3, n-5, ... — каждая вторая от конца.
            Array.Sort(sums);

            long sumP1 = 0;
            for (int i = n - 1; i >= 0; i -= 2)
                sumP1 += sums[i];

            long cost = sumP1 - totalB;

            // Вывод тоже через буферизованный поток — на маленькой строке некритично,
            // но избавляет от привязки к Console.Out с его CRLF/буферизацией.
            using (var stdout = new StreamWriter(Console.OpenStandardOutput()))
                stdout.WriteLine(cost);
        }
    }
}
