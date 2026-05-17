using System;
using System.Diagnostics;
namespace CW3.Utils;

public static class AlgorithmProfiler
{
    /// <summary>
    /// Результат профилирования алгоритма
    /// </summary>
    public class ProfileResult<T>
    {
        public string AlgorithmName { get; set; } = string.Empty;
        public TimeSpan ElapsedTime { get; set; }
        public TimeSpan AverageTime { get; set; }
        public long TotalMemoryBytes { get; set; }
        public long AverageMemoryBytes { get; set; }
        public long Iterations { get; set; }
        public long MinTimeTicks { get; set; }
        public long MaxTimeTicks { get; set; }
        public T[]? Result { get; set; }

        public override string ToString()
        {
            var firstResult = Result?.Length > 0 ? Result[0] : default;

            // ✅ Проверяем тип и вызываем нужный метод
            string resultString = firstResult switch
            {
                List<int> list => ListExtensions.ListToString(list),
                int[] array => ArrayUtils.ArrayToString(array),
                _ => firstResult?.ToString() ?? "0"
            };

            return $@"
════════════════════════════════════════
Алгоритм: {AlgorithmName}

Время (всего):     {ElapsedTime.TotalMilliseconds:F2} мс
Время (среднее):   {AverageTime.TotalMilliseconds:F4} мс
Время (мин):       {TimeSpan.FromTicks(MinTimeTicks).TotalMilliseconds:F4} мс
Время (макс):      {TimeSpan.FromTicks(MaxTimeTicks).TotalMilliseconds:F4} мс
Итераций:          {Iterations}
Память (всего):    {TotalMemoryBytes / 1024.0:F2} КБ ({TotalMemoryBytes / (1024.0 * 1024.0):F4} МБ)
Память (средняя):  {AverageMemoryBytes / 1024.0:F2} КБ ({AverageMemoryBytes / (1024.0 * 1024.0):F4} МБ)
Результат:         {resultString}
════════════════════════════════════════";
        }
    }

    /// <summary>
    /// Профилирование алгоритма без возврата значения (Action)
    /// </summary>
    public static ProfileResult<object> Profile(
        string algorithmName,
        Action algorithm,
        int iterations = 10,
        bool warmup = true)
    {
        if (iterations <= 0)
            throw new ArgumentException("Iterations must be positive", nameof(iterations));

        if (algorithm == null)
            throw new ArgumentNullException(nameof(algorithm));

        // Warmup
        if (warmup)
        {
            algorithm();
            GC.Collect();
            GC.WaitForPendingFinalizers();
        }

        GC.Collect();
        GC.WaitForPendingFinalizers();
        GC.Collect();

        long memoryBefore = GC.GetTotalMemory(true);

        var times = new long[iterations];
        var stopwatch = new Stopwatch();
        long totalTime = 0;
        long minTime = long.MaxValue;
        long maxTime = long.MinValue;

        for (int i = 0; i < iterations; i++)
        {
            stopwatch.Restart();
            algorithm();
            stopwatch.Stop();

            times[i] = stopwatch.ElapsedTicks;
            totalTime += times[i];
            minTime = Math.Min(minTime, times[i]);
            maxTime = Math.Max(maxTime, times[i]);
        }

        GC.Collect();
        GC.WaitForPendingFinalizers();
        GC.Collect();

        long memoryAfter = GC.GetTotalMemory(true);
        long memoryUsed = Math.Max(0, memoryAfter - memoryBefore);

        return new ProfileResult<object>
        {
            AlgorithmName = algorithmName,
            ElapsedTime = TimeSpan.FromTicks(totalTime),
            AverageTime = TimeSpan.FromTicks(totalTime / iterations),
            TotalMemoryBytes = memoryUsed,
            AverageMemoryBytes = memoryUsed / iterations,
            Iterations = iterations,
            MinTimeTicks = minTime,      // ✅ Добавлено
            MaxTimeTicks = maxTime,      // ✅ Добавлено
            Result = Array.Empty<object>() // ✅ Пустой массив для Action
        };
    }

    /// <summary>
    /// Профилирование алгоритма с возвратом значения (Func<T>)
    /// </summary>
    public static ProfileResult<T> Profile<T>(
        string algorithmName,
        Func<T> algorithm,
        int iterations = 10,
        bool warmup = true)
    {
        if (iterations <= 0)
            throw new ArgumentException("Iterations must be positive", nameof(iterations));

        if (algorithm == null)
            throw new ArgumentNullException(nameof(algorithm));

        // Warmup
        if (warmup)
        {
            algorithm();
            GC.Collect();
            GC.WaitForPendingFinalizers();
        }

        GC.Collect();
        GC.WaitForPendingFinalizers();
        GC.Collect();

        long memoryBefore = GC.GetTotalMemory(true);

        var times = new long[iterations];
        var stopwatch = new Stopwatch();
        long totalTime = 0;
        long minTime = long.MaxValue;
        long maxTime = long.MinValue;
        var results = new T[iterations];

        for (int i = 0; i < iterations; i++)
        {
            stopwatch.Restart();
            results[i] = algorithm();
            stopwatch.Stop();

            times[i] = stopwatch.ElapsedTicks;
            totalTime += times[i];
            minTime = Math.Min(minTime, times[i]);
            maxTime = Math.Max(maxTime, times[i]);
        }

        GC.Collect();
        GC.WaitForPendingFinalizers();
        GC.Collect();

        long memoryAfter = GC.GetTotalMemory(true);
        long memoryUsed = Math.Max(0, memoryAfter - memoryBefore);

        return new ProfileResult<T>
        {
            AlgorithmName = algorithmName,
            ElapsedTime = TimeSpan.FromTicks(totalTime),
            AverageTime = TimeSpan.FromTicks(totalTime / iterations),
            TotalMemoryBytes = memoryUsed,
            AverageMemoryBytes = memoryUsed / iterations,
            Iterations = iterations,
            MinTimeTicks = minTime,
            MaxTimeTicks = maxTime,
            Result = results
        };
    }
}